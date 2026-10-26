<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\ContentStatus;
use App\Modules\SearchIntelligence\Domain\Enums\CampaignStatus;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Modules\SearchIntelligence\Domain\Models\CampaignMetric;
use App\Modules\SearchIntelligence\Domain\Models\ContentItem;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-9 (#8520) — vue d'ensemble.
 *
 * Les trois garanties testées ici sont celles de l'audit produit :
 *  - les KPI sont RÉELS (insérés en base, relus dans la réponse) ;
 *  - les états non connectés sont affichés comme tels (`null` / `not_connected`),
 *    jamais déguisés en `0` ;
 *  - chaque KPI porte source / période / fraîcheur / fiabilité.
 */
final class OverviewTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsOps(): void
    {
        $admin = new SuperAdmin([
            'name' => 'Ops',
            'email' => 'ops-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('x'),
            'platform_role' => PlatformRole::Ops->value,
        ])->save();

        Sanctum::actingAs($admin->refresh(), ['*'], 'super_admin_api');
    }

    private function makeCampaign(string $name, CampaignStatus $status): Campaign
    {
        $campaign = new Campaign;
        $campaign->fill(['name' => $name, 'channel' => 'google_ads', 'currency' => 'EUR']);
        $campaign->status = $status;

        // Une campagne `active` sans validation est interdite par la contrainte
        // CHECK (campaigns_active_requires_validation). On pose donc la preuve
        // de validation quand le scénario demande une campagne qui tourne —
        // c'est exactement ce que ferait le vrai chemin `validate()`.
        if ($status === CampaignStatus::Active) {
            $campaign->validated_at = Carbon::now();
        }

        $campaign->save();

        return $campaign;
    }

    public function test_every_kpi_carries_source_period_freshness_and_reliability(): void
    {
        $this->actingAsOps();

        $overview = $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertOk()
            ->json('data');

        // Un KPI est un objet tracé, pas un scalaire nu.
        foreach (['pages_tracked', 'pages_healthy', 'open_issues', 'verified_issues'] as $key) {
            $kpi = $overview['seo'][$key];

            self::assertArrayHasKey('value', $kpi, "seo.{$key}.value manquant");
            self::assertArrayHasKey('source', $kpi, "seo.{$key}.source manquant");
            self::assertArrayHasKey('period', $kpi, "seo.{$key}.period manquant");
            self::assertArrayHasKey('updated_at', $kpi, "seo.{$key}.updated_at manquant");
            self::assertArrayHasKey('reliability', $kpi, "seo.{$key}.reliability manquant");
            self::assertContains($kpi['reliability'], ['measured', 'declared', 'unavailable']);
        }

        self::assertNotNull($overview['generated_at']);
    }

    public function test_real_counters_reflect_the_database(): void
    {
        $this->makeCampaign('A', CampaignStatus::Active);
        $this->makeCampaign('B', CampaignStatus::Paused);

        $item = new ContentItem;
        $item->fill(['title' => 'Guide paie', 'kind' => 'guide', 'locale' => 'fr']);
        // `published` exige une date de validation et une URL cible (contrainte
        // CHECK content_items_publish_requires_author_and_date) : on pose les
        // preuves comme le ferait la vraie transition.
        $item->forceFill([
            'status' => ContentStatus::Published->value,
            'target_url' => 'https://x.y/z',
            'approved_at' => Carbon::now(),
            'published_at' => Carbon::now(),
        ])->save();

        $this->actingAsOps();

        $sea = $this->getJson('/api/v1/platform/search-intel/overview')->assertOk()->json('data.sea');
        self::assertSame(2, $sea['campaigns_total']['value']);
        self::assertSame(1, $sea['campaigns_running']['value']);

        $content = $this->getJson('/api/v1/platform/search-intel/overview')->assertOk()->json('data.content');
        self::assertSame(1, $content['items_total']['value']);
        self::assertSame(1, $content['by_status']['published']['value']);
        self::assertSame(0, $content['by_status']['draft']['value']);
    }

    public function test_month_spend_is_null_when_no_metric_recorded(): void
    {
        $this->actingAsOps();

        $sea = $this->getJson('/api/v1/platform/search-intel/overview')->assertOk()->json('data.sea');

        // Aucun relevé ce mois-ci : « pas de donnée », pas « zéro euro ».
        self::assertNull($sea['spend_month_cents']['value']);
        self::assertSame('unavailable', $sea['spend_month_cents']['reliability']);
    }

    public function test_month_spend_sums_recorded_metrics_only(): void
    {
        $campaign = $this->makeCampaign('A', CampaignStatus::Active);
        $now = Carbon::now();

        CampaignMetric::query()->create([
            'campaign_id' => $campaign->id,
            'measured_on' => $now->copy()->startOfMonth()->toDateString(),
            'source' => 'manual',
            'spend_cents' => 1234,
            'impressions' => 100,
            'clicks' => 5,
            'conversions' => 1,
            'revenue_cents' => 0,
        ]);

        // Hors période : ne doit pas compter.
        CampaignMetric::query()->create([
            'campaign_id' => $campaign->id,
            'measured_on' => $now->copy()->subMonths(2)->toDateString(),
            'source' => 'manual',
            'spend_cents' => 9999,
            'impressions' => 100,
            'clicks' => 5,
            'conversions' => 1,
            'revenue_cents' => 0,
        ]);

        $this->actingAsOps();

        $sea = $this->getJson('/api/v1/platform/search-intel/overview')->assertOk()->json('data.sea');

        self::assertSame(1234, $sea['spend_month_cents']['value']);
        // Saisie humaine : exact sur ce qui a été saisi, d'où « declared ».
        self::assertSame('declared', $sea['spend_month_cents']['reliability']);
    }

    public function test_not_connected_integrations_are_declared_and_carry_no_data(): void
    {
        $this->actingAsOps();

        $integrations = $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertOk()
            ->json('data.integrations');

        // Les quatre lignes attendues sont présentes même sans configuration :
        // un écran vide serait ambigu, quatre lignes « non connecté » sont
        // informatives.
        self::assertCount(4, $integrations);

        $providers = array_column($integrations, 'provider');
        self::assertSame(['gsc', 'ga4', 'google_ads', 'ms_ads'], $providers);

        foreach ($integrations as $row) {
            self::assertFalse($row['connected']);
            self::assertSame('not_connected', $row['status']);
            self::assertSame('unavailable', $row['reliability']);
            self::assertNull($row['last_synced_at']);
        }
    }

    public function test_connected_integration_is_reflected(): void
    {
        Integration::query()->create([
            'provider' => 'gsc',
            'status' => 'connected',
            'last_synced_at' => Carbon::now(),
        ]);

        $this->actingAsOps();

        $integrations = $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertOk()
            ->json('data.integrations');

        $gsc = null;

        foreach ($integrations as $row) {
            if ($row['provider'] === 'gsc') {
                $gsc = $row;
            }
        }

        self::assertNotNull($gsc);
        self::assertTrue($gsc['connected']);
        self::assertSame('measured', $gsc['reliability']);
        self::assertNotNull($gsc['last_synced_at']);
    }

    public function test_leads_are_unavailable_rather_than_zero(): void
    {
        $this->actingAsOps();

        $leads = $this->getJson('/api/v1/platform/search-intel/overview')->assertOk()->json('data.leads');

        // Aucun provider de leads branché : `null` et non `0`, pour que
        // l'écran puisse distinguer « pas de données » de « aucun lead ».
        self::assertFalse($leads['available']);
        self::assertSame('unavailable', $leads['reliability']);
        self::assertNull($leads['leads_total']['value']);
        self::assertSame(30, $leads['window_days']);
        self::assertNotNull($leads['period']);
    }

    public function test_overview_answers_under_500ms(): void
    {
        // Volume représentatif : c'est le budget de latence annoncé dans la
        // spec, et il doit tenir sur une base déjà peuplée.
        for ($i = 0; $i < 20; $i++) {
            $this->makeCampaign('C'.$i, CampaignStatus::Draft);
        }

        $this->actingAsOps();

        $start = microtime(true);
        $this->getJson('/api/v1/platform/search-intel/overview')->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        self::assertLessThan(500, $elapsedMs, "overview a pris {$elapsedMs} ms");
    }
}
