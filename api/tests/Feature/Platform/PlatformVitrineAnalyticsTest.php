<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7595 — écran admin d'analytique de la vitrine.
 *
 * Couvre : auth super-admin requise, agrégats de trafic, top pages, sources
 * (avec le groupe `direct` pour l'attribution absente), taux de passage par
 * étape du tunnel — et surtout le cas où le taux vaut `null` plutôt que `0`
 * quand l'étape amont n'a aucun événement —, et les derniers leads vitrine
 * devenus consultables.
 */
class PlatformVitrineAnalyticsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsSuperAdmin(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Vitrine',
            'email' => 'sa-vitrine@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    private function insertView(string $day, string $path, ?string $source, int $views): void
    {
        DB::table('vitrine_page_views')->insert([
            'day' => $day,
            'page_path' => $path,
            'source' => $source,
            'views' => $views,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertFunnelStep(string $event, string $correlationId): void
    {
        DB::table('acquisition_funnel_events')->insert([
            'event' => $event,
            'correlation_id' => $correlationId,
            'occurred_at' => now()->subDays(1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_analytics_require_super_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/vitrine/analytics')->assertStatus(401);
    }

    public function test_it_aggregates_traffic_top_pages_and_sources(): void
    {
        $this->actingAsSuperAdmin();

        $today = now()->toDateString();
        $this->insertView($today, '/', null, 10);
        $this->insertView($today, '/pricing', 'google', 5);
        $this->insertView($today, '/pricing', 'facebook', 3);
        $this->insertView($today, '/blog/post-1', 'google', 2);

        $response = $this->getJson('/api/v1/admin/vitrine/analytics?days=7')->assertOk();

        $response->assertJsonPath('data.traffic.views', 20);
        $response->assertJsonPath('data.traffic.pages', 3);

        // La page d'accueil totalise 10 vues ; `/pricing` en cumule 8
        // (google + facebook) et arrive donc en second.
        $response->assertJsonPath('data.top_pages.0.path', '/');
        $response->assertJsonPath('data.top_pages.0.views', 10);
        $response->assertJsonPath('data.top_pages.1.path', '/pricing');
        $response->assertJsonPath('data.top_pages.1.views', 8);

        // `direct` regroupe les vues sans source : c'est un groupe réel.
        $sources = collect($response->json('data.sources'))->keyBy('source');
        $this->assertSame(10, $sources['direct']['views']);
        $this->assertSame(7, $sources['google']['views']);
    }

    public function test_conversion_returns_null_rate_when_previous_step_has_no_events(): void
    {
        $this->actingAsSuperAdmin();

        // Aucun événement sur la période : tous les taux doivent être `null`,
        // jamais `0` — l'écran affiche « — », pas « 0 % ».
        $response = $this->getJson('/api/v1/admin/vitrine/analytics?days=7')->assertOk();

        $steps = $response->json('data.conversion');
        $this->assertCount(5, $steps);

        foreach ($steps as $step) {
            $this->assertSame(0, $step['events']);
            $this->assertNull($step['rate_from_previous']);
            $this->assertNull($step['rate_from_start']);
        }
    }

    public function test_conversion_computes_rates_from_distinct_journeys(): void
    {
        $this->actingAsSuperAdmin();

        // 4 parcours voient la page d'inscription, 2 soumettent leur e-mail.
        foreach (['j1', 'j2', 'j3', 'j4'] as $journey) {
            $this->insertFunnelStep('signup_view', $journey);
        }
        foreach (['j1', 'j2'] as $journey) {
            $this->insertFunnelStep('signup_email_submitted', $journey);
        }

        $response = $this->getJson('/api/v1/admin/vitrine/analytics?days=7')->assertOk();

        $steps = collect($response->json('data.conversion'))->keyBy('step');

        $this->assertSame(4, $steps['signup_view']['events']);
        $this->assertSame(2, $steps['signup_email_submitted']['events']);
        $this->assertSame(1, $steps['signup_view']['rate_from_start']);
        $this->assertSame(0.5, $steps['signup_email_submitted']['rate_from_start']);

        // Étape suivante sans événement : le ratio par rapport à l'étape
        // précédente vaut 0.0 (le dénominateur existe), pas null.
        $this->assertSame(0, $steps['signup_otp_sent']['rate_from_previous']);
    }

    public function test_recent_leads_become_visible(): void
    {
        $this->actingAsSuperAdmin();

        DB::table('marketing_leads')->insert([
            'external_id' => 'lead-ext-1',
            'type' => 'demo_request',
            'email' => 'prospect@example.com',
            'source' => 'linkedin',
            'campaign' => 'q4-demo',
            'page' => '/demo',
            'status' => 'new',
            'payload' => json_encode(['name' => 'Amina B.', 'company' => 'ACME SARL']),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/admin/vitrine/analytics?days=7')->assertOk();

        $response->assertJsonPath('data.recent_leads.0.type', 'demo_request');
        $response->assertJsonPath('data.recent_leads.0.email', 'prospect@example.com');
        $response->assertJsonPath('data.recent_leads.0.name', 'Amina B.');
        $response->assertJsonPath('data.recent_leads.0.company_name', 'ACME SARL');
    }

    public function test_period_is_bounded_and_echoed_back(): void
    {
        $this->actingAsSuperAdmin();

        // 1000 jours demandés : borné à 90, et la réponse le dit.
        $response = $this->getJson('/api/v1/admin/vitrine/analytics?days=1000')->assertOk();

        $response->assertJsonPath('data.period.days', 90);
    }
}
