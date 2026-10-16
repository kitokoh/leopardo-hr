<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Marketing OS — publication directe vs assistee (issue #8532).
 *
 * Le brief §F interdit deux choses que ces tests verrouillent :
 *
 *  - « Ne pas pretendre qu'une publication est automatisee si elle necessite
 *    encore une intervention manuelle » : la matrice des capacites declare
 *    plateforme par plateforme si un connecteur existe REELLEMENT, et
 *    pourquoi les autres restent en mode assiste ;
 *  - « Ne pas conserver de mots de passe de reseaux sociaux en clair » :
 *    l'autorisation passe par une Profile Key conservee CHIFFREE.
 *
 * Le brief §E exige aussi que les erreurs et l'historique des tentatives
 * soient conserves : un echec alimente `error_message` et `attempt_history`
 * sans jamais ecraser l'historique precedent.
 */
class MarketingPublishingCapabilityTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function actingAsPlatform(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        /** @var SuperAdmin $account */
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => 'marketing-capability-'.$role->value.'@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    private function url(Company $company, string $path = ''): string
    {
        return "/api/v1/admin/companies/{$company->id}/marketing-os{$path}";
    }

    private function tenants(): TenantManager
    {
        return app(TenantManager::class);
    }

    /** @param \Closure(): mixed $callback */
    private function inTenant(Company $company, \Closure $callback): mixed
    {
        return $this->tenants()->withinTenant($company, $callback);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. La matrice dit la VERITE sur les capacites de chaque plateforme
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_capability_matrix_distinguishes_direct_from_assisted(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $response = $this->getJson($this->url($company, '/publishing-capabilities'))
            ->assertOk();

        /** @var array<int, array{platform: string, direct: bool, reason: string|null}> $rows */
        $rows = $response->json('data');
        $byPlatform = [];

        foreach ($rows as $row) {
            $byPlatform[$row['platform']] = $row;
        }

        // Plateformes couvertes par le connecteur existant : direct.
        foreach (['linkedin', 'facebook_page', 'instagram', 'twitter'] as $platform) {
            $this->assertArrayHasKey($platform, $byPlatform);
            $this->assertTrue($byPlatform[$platform]['direct'], "{$platform} doit etre en direct.");
            $this->assertNull($byPlatform[$platform]['reason'], "{$platform} n a pas de motif d assistance.");
        }

        // YouTube et TikTok n'ont PAS de connecteur : le mode assiste est
        // annonce explicitement, avec son motif — jamais une fausse
        // automatisation.
        foreach (['youtube', 'tiktok'] as $platform) {
            $this->assertArrayHasKey($platform, $byPlatform);
            $this->assertFalse($byPlatform[$platform]['direct'], "{$platform} ne doit pas etre annonce en direct.");
            $this->assertNotNull($byPlatform[$platform]['reason']);
            $this->assertStringContainsString('assistee', (string) $byPlatform[$platform]['reason']);
        }
    }

    public function test_the_matrix_explains_the_effective_mode(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // Sans compte social connecte, la publication directe n'est pas
        // disponible : le mode effectif est `assisted` et l'API l'explique.
        $response = $this->getJson($this->url($company, '/publishing-capabilities'))
            ->assertOk()
            ->assertJsonPath('meta.direct_available', false)
            ->assertJsonPath('meta.mode', 'assisted');

        $this->assertNotSame('', (string) $response->json('meta.mode_explanation'));
    }

    public function test_the_matrix_is_readable_with_the_view_permission_only(): void
    {
        $company = $this->company();
        $this->actingAsPlatform(PlatformRole::Marketing);

        // `marketing` detient `marketing_os.view` : la lecture passe.
        $this->getJson($this->url($company, '/publishing-capabilities'))->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Aucun secret de reseau social en clair
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_provider_profile_key_is_encrypted_at_rest(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $plaintext = 'ayrshare-profile-key-SECRET-8532';

        $this->inTenant($company, function () use ($company, $plaintext): void {
            SocialAccount::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'provider' => 'ayrshare',
                'provider_profile_ref' => $plaintext,
                'status' => 'active',
            ]);
        });

        // La valeur BRUTE en base ne doit pas contenir le secret : elle est
        // chiffree par le cast `encrypted` du modele.
        $raw = $this->inTenant($company, function (): string {
            /** @var object{provider_profile_ref: string} $row */
            $row = DB::table('social_accounts')->first();

            return (string) $row->provider_profile_ref;
        });

        $this->assertNotSame($plaintext, $raw, 'La Profile Key ne doit jamais etre stockee en clair.');
        $this->assertStringNotContainsString('SECRET-8532', $raw);

        // ...et le modele la restitue correctement a l'application.
        $decrypted = $this->inTenant($company, function (): string {
            /** @var SocialAccount $account */
            $account = SocialAccount::withoutGlobalScopes()->firstOrFail();

            return (string) $account->provider_profile_ref;
        });

        $this->assertSame($plaintext, $decrypted);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Un echec est conserve : message + historique des tentatives
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_failed_publication_records_the_error_and_keeps_the_attempt_history(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'youtube',
            'title' => 'Publicite restaurant',
        ])->assertCreated()->json('data.id');

        // Premier echec : quota de l'API atteint.
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'failed',
            'error_message' => 'Quota API depasse (429).',
        ])->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_message', 'Quota API depasse (429).');

        // Second echec : le message est remplace, mais l'HISTORIQUE conserve
        // les deux tentatives (le brief interdit d'ecraser l'historique).
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'failed',
            'error_message' => 'Jeton d autorisation expire.',
        ])->assertOk()
            ->assertJsonPath('data.error_message', 'Jeton d autorisation expire.');

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            $this->assertSame(2, $publication->attempts);
            $this->assertCount(2, (array) $publication->attempt_history);

            $outcomes = array_column((array) $publication->attempt_history, 'outcome');
            $this->assertSame(['failed', 'failed'], $outcomes);

            $messages = array_column((array) $publication->attempt_history, 'error');
            $this->assertContains('Quota API depasse (429).', $messages);
            $this->assertContains('Jeton d autorisation expire.', $messages);
        });
    }

    public function test_a_failure_requires_an_error_message(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Sans motif',
        ])->assertCreated()->json('data.id');

        // Un echec sans motif serait inexploitable pour l'operateur.
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'failed',
        ])->assertStatus(422)->assertJsonValidationErrors(['error_message']);
    }

    public function test_a_successful_publication_clears_the_previous_error(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Nouvelle tentative',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'failed',
            'error_message' => 'Erreur transitoire.',
        ])->assertOk();

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.linkedin.com/posts/leopardo-8532',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.error_message', null);

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            // L'erreur est effacee, mais la trace de la tentative echouee
            // reste dans l'historique.
            $this->assertNull($publication->error_message);
            $this->assertCount(2, (array) $publication->attempt_history);
        });
    }
}
