<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Marketing OS — statistiques REELLES des plateformes (issue #8530).
 *
 * Le brief interdit formellement d'inventer un indicateur : « Chaque
 * indicateur doit provenir d'une donnée réelle ou être explicitement
 * identifié comme donnée de démonstration » (§A) et « Les indicateurs doivent
 * être mesurés ou clairement identifiés comme estimations » (§H).
 *
 * Ces tests verrouillent cette garantie sur les trois issues possibles :
 *
 *  1. l'API renvoie des indicateurs → `stats_source = platform` ;
 *  2. la plateforme n'en fournit pas (pas d'API, pas de connecteur, pas
 *     d'identifiant externe, réponse vide) → `stats_source = unavailable`
 *     avec un motif, et AUCUN chiffre de repli ;
 *  3. l'appel échoue → même état `unavailable`, sans écraser une mesure
 *     antérieure.
 *
 * Un relevé saisi par un humain reste étiqueté `manual` : il n'est jamais
 * présenté comme une mesure de la plateforme.
 */
class MarketingPublicationStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const ANALYTICS_URL = 'api.ayrshare.com/api/analytics/post';

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
            'email' => 'marketing-stats-'.$role->value.'@leopardo.test',
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

    private function connectedAccount(Company $company): SocialAccount
    {
        /** @var SocialAccount $account */
        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-stats',
            'status' => 'active',
        ]);

        return $account;
    }

    /**
     * Cree une publication PUBLIEE avec un identifiant externe, en passant par
     * l'API reelle (aucune ecriture directe en base) : c'est le chemin qu'un
     * operateur emprunte, et il garantit que `external_post_id` est bien
     * persiste par la confirmation de publication.
     */
    private function publishedPublication(Company $company, string $platform = 'linkedin'): int
    {
        $id = $this->postJson($this->url($company, '/publications'), [
            'platform' => $platform,
            'title' => 'Publicite restaurant',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$id}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.linkedin.com/posts/leopardo-123',
            'external_post_id' => 'ayr-post-8530',
        ])->assertOk();

        return (int) $id;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. La donnee mesuree est enregistree telle quelle
    // ─────────────────────────────────────────────────────────────────────

    public function test_platform_stats_are_recorded_when_the_api_returns_them(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        $publicationId = $this->publishedPublication($company);

        Http::fake([
            self::ANALYTICS_URL => Http::response([
                'status' => 'success',
                'analytics' => [
                    'linkedin' => [
                        'impressions' => 1200,
                        'clicks' => 34,
                        'likes' => 57,
                        'comments' => 8,
                        'shares' => 5,
                    ],
                ],
            ], 200),
        ]);

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertOk()
            ->assertJsonPath('meta.measured', true)
            ->assertJsonPath('meta.stats_source', 'platform')
            ->assertJsonPath('data.has_measured_stats', true)
            ->assertJsonPath('data.stats.impressions', 1200)
            ->assertJsonPath('data.stats.clicks', 34);

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            /** @var array<string, mixed> $stats */
            $stats = $publication->stats;

            $this->assertSame('platform', $publication->stats_source);
            $this->assertSame(1200, $stats['impressions']);
            $this->assertNull($publication->stats_error);
            $this->assertNotNull($publication->stats_fetched_at, 'La date de recuperation doit etre tracee.');
            $this->assertTrue($publication->hasMeasuredStats());
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Aucune donnee disponible : on le DIT, on n'invente rien
    // ─────────────────────────────────────────────────────────────────────

    public function test_stats_are_declared_unavailable_when_the_platform_returns_none(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        $publicationId = $this->publishedPublication($company);

        // L'agregateur repond, mais sans aucune metrique pour cette plateforme.
        Http::fake([
            self::ANALYTICS_URL => Http::response([
                'status' => 'success',
                'analytics' => ['linkedin' => []],
            ], 200),
        ]);

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertOk()
            ->assertJsonPath('meta.measured', false)
            ->assertJsonPath('meta.stats_source', 'unavailable')
            ->assertJsonPath('data.has_measured_stats', false)
            ->assertJsonPath('data.stats', null);

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            $this->assertSame('unavailable', $publication->stats_source);
            $this->assertNull($publication->stats, 'Aucun chiffre ne doit etre invente.');
            $this->assertNotNull($publication->stats_error, 'Le motif de l indisponibilite doit etre conserve.');
            $this->assertNotNull($publication->stats_fetched_at);
        });
    }

    public function test_stats_are_unavailable_without_an_external_post_id_and_no_api_call_is_made(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        // Publication creee mais JAMAIS executee par une API : aucun
        // identifiant externe, donc rien a interroger.
        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Brouillon non publie',
        ])->assertCreated()->json('data.id');

        Http::fake();

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertOk()
            ->assertJsonPath('meta.measured', false)
            ->assertJsonPath('meta.stats_source', 'unavailable');

        // Aucun appel reseau inutile : on ne devine pas un identifiant.
        Http::assertNothingSent();
    }

    public function test_stats_are_unavailable_when_the_platform_has_no_connector(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        // YouTube n'est pas couvert par l'agregateur : la donnee est declaree
        // indisponible, jamais estimee (extension suivie par #8532).
        $publicationId = $this->publishedPublication($company, 'youtube');

        Http::fake();

        $response = $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertOk()
            ->assertJsonPath('meta.measured', false)
            ->assertJsonPath('meta.stats_source', 'unavailable');

        $this->assertStringContainsString('youtube', (string) $response->json('meta.stats_error'));
        Http::assertNothingSent();
    }

    public function test_api_failure_is_recorded_as_unavailable_without_inventing_values(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        $publicationId = $this->publishedPublication($company);

        Http::fake([
            self::ANALYTICS_URL => Http::response([
                'status' => 'error',
                'errors' => [['message' => 'rate limited']],
            ], 429),
        ]);

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertOk()
            ->assertJsonPath('meta.measured', false)
            ->assertJsonPath('meta.stats_source', 'unavailable');

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            $this->assertSame('unavailable', $publication->stats_source);
            $this->assertNull($publication->stats);
            $this->assertStringContainsString('rate limited', (string) $publication->stats_error);
        });
    }

    public function test_a_previous_measurement_survives_a_later_failed_fetch(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);
        $this->actingAsPlatform();

        $publicationId = $this->publishedPublication($company);

        // `Http::fake()` FUSIONNE les stubs (le premier motif correspondant
        // gagne) : deux appels successifs ne remplacent pas la reponse. On
        // utilise donc une sequence, comme PostPublicationTest.
        Http::fake([
            self::ANALYTICS_URL => Http::sequence()
                ->push([
                    'status' => 'success',
                    'analytics' => ['linkedin' => ['impressions' => 900, 'clicks' => 12]],
                ], 200)
                ->push(['status' => 'error'], 500),
        ]);

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))->assertOk();

        // Deuxieme tentative : l'API tombe. La mesure precedente ne doit PAS
        // etre detruite ni remplacee par une estimation.
        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))->assertOk();

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            /** @var array<string, mixed> $stats */
            $stats = $publication->stats;

            $this->assertSame('unavailable', $publication->stats_source);
            $this->assertSame(900, $stats['impressions'], 'La mesure anterieure doit rester consultable.');
            $this->assertFalse($publication->hasMeasuredStats(), 'Une donnee non rafraichie n est pas presentee comme mesuree.');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Un releve humain n'est jamais presente comme une mesure d'API
    // ─────────────────────────────────────────────────────────────────────

    public function test_manually_entered_stats_are_labelled_manual_never_platform(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'youtube',
            'title' => 'Publicite restaurant',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.youtube.com/watch?v=abcd1234',
            'stats' => ['views' => 4200, 'likes' => 310],
        ])->assertOk();

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            /** @var array<string, mixed> $stats */
            $stats = $publication->stats;

            $this->assertSame('manual', $publication->stats_source);
            $this->assertSame(4200, $stats['views']);
            // `hasMeasuredStats()` est reserve a la provenance `platform` :
            // un releve humain n'est pas une mesure d'API.
            $this->assertFalse($publication->hasMeasuredStats());
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Permissions : la lecture ne suffit pas a declencher un appel API
    // ─────────────────────────────────────────────────────────────────────

    public function test_refreshing_stats_requires_the_manage_permission(): void
    {
        $company = $this->company();
        $this->connectedAccount($company);

        // La publication est creee par un super admin (l'ecriture exige
        // `marketing_os.manage`)...
        $this->actingAsPlatform();
        $publicationId = $this->publishedPublication($company);

        // ...puis on repasse avec le role plateforme `marketing`, qui ne
        // detient que `marketing_os.view` : declencher un appel API sortant
        // reste une action d'administration.
        $this->actingAsPlatform(PlatformRole::Marketing);

        Http::fake();

        $this->postJson($this->url($company, "/publications/{$publicationId}/stats/refresh"))
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
