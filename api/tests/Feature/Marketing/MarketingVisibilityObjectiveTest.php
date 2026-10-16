<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Models\MarketingVisibilityObjective;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Marketing OS — visibilite SEO/SEA/AEO/GEO liee aux contenus (issue #8531).
 *
 * Le brief §H impose trois choses que ces tests verrouillent :
 *
 *  1. un contenu peut etre relie a un objectif de visibilite (mot-cle,
 *     intention, page de destination, campagne) ;
 *  2. les indicateurs sont MESURES ou explicitement identifies comme
 *     estimations — jamais un chiffre presente comme un fait sans provenance ;
 *  3. aucune visibilite garantie n'est promise dans les moteurs de recherche
 *     ou les assistants IA.
 *
 * L'audit #8528 a etabli que le centre de visibilite existant vit cote
 * vitrine et qu'il n'existe aucune donnee exploitable cote administration :
 * ces tests verifient donc que l'on stocke l'INTENTION et la MESURE, en
 * referencant la page de destination — sans creer de systeme parallele.
 */
class MarketingVisibilityObjectiveTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    private function company(?string $name = null): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create($name !== null ? ['name' => $name] : []);

        return $company;
    }

    private function actingAsPlatform(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        /** @var SuperAdmin $account */
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => 'marketing-visibility-'.$role->value.'@leopardo.test',
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
    // 1. Un contenu se relie a un objectif de visibilite
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_content_idea_can_be_linked_to_a_visibility_objective(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $ideaId = $this->postJson($this->url($company, '/ideas'), [
            'title' => 'Article : choisir son logiciel de gestion',
            'objective' => 'acquisition',
            'format' => 'article',
            'locale' => 'fr',
        ])->assertCreated()->json('data.id');

        $response = $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'seo',
            'keyword' => 'logiciel de gestion PME',
            'search_intent' => 'commercial',
            'destination_url' => 'https://leopardo.example/fr/logiciel-gestion',
            'content_idea_id' => $ideaId,
            'locale' => 'fr',
            'questions_answered' => ['Quel logiciel choisir pour une PME ?'],
        ])->assertCreated()
            ->assertJsonPath('data.channel', 'seo')
            ->assertJsonPath('data.keyword', 'logiciel de gestion PME')
            ->assertJsonPath('data.content_idea_id', $ideaId)
            ->assertJsonPath('data.has_linked_content', true);

        // Aucun indicateur fourni : la provenance est `unavailable`, jamais
        // une mesure implicite.
        $this->assertSame('unavailable', $response->json('data.metrics_source'));
        $this->assertFalse($response->json('data.has_measured_metrics'));
    }

    public function test_all_four_channels_are_accepted_and_an_unknown_one_is_rejected(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        foreach (['seo', 'sea', 'aeo', 'geo'] as $channel) {
            $this->postJson($this->url($company, '/visibility-objectives'), [
                'channel' => $channel,
                'keyword' => 'mot cle '.$channel,
            ])->assertCreated()->assertJsonPath('data.channel', $channel);
        }

        // Canal hors liste fermee : refuse (aucun canal « autre » implicite).
        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'not_a_channel',
        ])->assertStatus(422)->assertJsonValidationErrors(['channel']);
    }

    public function test_an_objective_can_exist_before_its_content_is_produced(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // L'intention precede legitimement la production : l'objectif est
        // cree sans contenu, et l'interface le signale comme « a produire ».
        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'geo',
            'keyword' => 'meilleur logiciel de gestion',
            'questions_answered' => ['Quel est le meilleur logiciel de gestion ?'],
        ])->assertCreated()
            ->assertJsonPath('data.has_linked_content', false);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Les indicateurs sont mesures ou declares estimations
    // ─────────────────────────────────────────────────────────────────────

    public function test_measured_metrics_are_labelled_measured(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'seo',
            'keyword' => 'logiciel de gestion PME',
            'metrics' => ['impressions' => 12000, 'clicks' => 340, 'position' => 4.2],
            'metrics_source' => 'measured',
            'metrics_note' => 'Search Console, 30 derniers jours.',
        ])->assertCreated()
            ->assertJsonPath('data.metrics_source', 'measured')
            ->assertJsonPath('data.has_measured_metrics', true)
            ->assertJsonPath('data.metrics.impressions', 12000);
    }

    public function test_estimated_metrics_are_never_presented_as_measured(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'geo',
            'keyword' => 'assistant IA logiciel gestion',
            'metrics' => ['estimated_citations' => 3],
            'metrics_source' => 'estimated',
            'metrics_note' => 'Estimation manuelle, non mesurable automatiquement.',
        ])->assertCreated()
            ->assertJsonPath('data.metrics_source', 'estimated')
            // Une estimation n'est PAS une mesure : le predicat reste faux.
            ->assertJsonPath('data.has_measured_metrics', false);
    }

    public function test_metrics_without_a_declared_source_fall_back_to_unavailable(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // Un chiffre sans provenance declaree ne doit JAMAIS etre presente
        // comme un fait : on retombe sur `unavailable` (fail-closed).
        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'sea',
            'keyword' => 'campagne payante',
            'metrics' => ['impressions' => 9999],
        ])->assertCreated()
            ->assertJsonPath('data.metrics_source', 'unavailable')
            ->assertJsonPath('data.has_measured_metrics', false);
    }

    public function test_an_invalid_metrics_source_is_rejected(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'seo',
            'metrics' => ['impressions' => 1],
            'metrics_source' => 'guessed',
        ])->assertStatus(422)->assertJsonValidationErrors(['metrics_source']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Aucune visibilite garantie n'est promise
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_listing_states_explicitly_that_no_visibility_is_guaranteed(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $response = $this->getJson($this->url($company, '/visibility-objectives'))
            ->assertOk()
            ->assertJsonPath('meta.channels', ['seo', 'sea', 'aeo', 'geo']);

        $guarantee = (string) $response->json('meta.guarantee');
        $this->assertNotSame('', $guarantee);
        $this->assertStringContainsString('garantie', $guarantee);
    }

    public function test_objectives_can_be_filtered_by_channel(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/visibility-objectives'), ['channel' => 'seo', 'keyword' => 'a'])->assertCreated();
        $this->postJson($this->url($company, '/visibility-objectives'), ['channel' => 'geo', 'keyword' => 'b'])->assertCreated();

        $this->getJson($this->url($company, '/visibility-objectives?channel=geo'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'geo');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Isolation tenant et permissions
    // ─────────────────────────────────────────────────────────────────────

    public function test_an_objective_cannot_be_linked_to_another_companys_idea(): void
    {
        $company = $this->company();
        $other = $this->company('Autre entreprise');
        $this->actingAsPlatform();

        $foreignIdeaId = $this->postJson($this->url($other, '/ideas'), [
            'title' => 'Idee de l autre entreprise',
            'objective' => 'notoriete',
            'format' => 'image',
            'locale' => 'fr',
        ])->assertCreated()->json('data.id');

        // Le rattachement inter-tenant est refuse : l'isolation tient.
        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'seo',
            'keyword' => 'fuite',
            'content_idea_id' => $foreignIdeaId,
        ])->assertStatus(422);

        $this->inTenant($company, function (): void {
            $this->assertSame(0, MarketingVisibilityObjective::query()->count());
        });
    }

    public function test_reading_objectives_requires_the_view_permission(): void
    {
        $company = $this->company();
        $this->actingAsPlatform(PlatformRole::Support);

        // `support` ne detient pas `marketing_os.view`.
        $this->getJson($this->url($company, '/visibility-objectives'))->assertForbidden();
    }

    public function test_writing_an_objective_requires_the_manage_permission(): void
    {
        $company = $this->company();
        $this->actingAsPlatform(PlatformRole::Marketing);

        // `marketing` lit le Marketing OS mais n'ecrit pas.
        $this->postJson($this->url($company, '/visibility-objectives'), [
            'channel' => 'seo',
            'keyword' => 'lecture seule',
        ])->assertForbidden();
    }
}
