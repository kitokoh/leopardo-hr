<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\CampaignStatus;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-3 (#8514) — campagnes SEA.
 *
 * Le test qui compte le plus est `test_draft_cannot_go_straight_to_active` :
 * il protège la règle « pas de dépense sans validation humaine ». Les autres
 * couvrent les relevés (unicité, provenance), les calculs et l'alerte budget.
 */
final class SeaCampaignTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsMarketing(): void
    {
        $admin = new SuperAdmin([
            'name' => 'Marketing',
            'email' => 'marketing-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('x'),
            'platform_role' => PlatformRole::Marketing->value,
        ])->save();

        Sanctum::actingAs($admin->refresh(), ['*'], 'super_admin_api');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCampaign(array $attributes = []): Campaign
    {
        $campaign = new Campaign;
        $campaign->fill(array_merge([
            'name' => 'SEA paie restaurant',
            'channel' => 'google_ads',
            'objective' => 'leads',
            'daily_budget_cents' => 5000,
            'currency' => 'EUR',
        ], $attributes));
        $campaign->status = CampaignStatus::Draft;
        $campaign->save();

        return $campaign;
    }

    public function test_crud_and_status_default(): void
    {
        $this->actingAsMarketing();

        $created = $this->postJson('/api/v1/platform/search-intel/sea/campaigns', [
            'name' => 'SEA paie restaurant',
            'channel' => 'google_ads',
            'objective' => 'leads',
            'daily_budget_cents' => 5000,
            'audience' => ['countries' => ['FR', 'TR'], 'languages' => ['fr', 'tr']],
            'utm_campaign' => 'paie-resto-fr',
            'landing_page' => 'https://leopardo-rh.com/paie-restaurant',
        ])->assertStatus(201)->json('data');

        // Une campagne naît toujours en brouillon : le statut n'est pas
        // mass-assignable, et il doit être présent (non null) dans la réponse.
        self::assertSame('draft', $created['status']);
        self::assertSame('manual', $created['tracking_mode']);
        self::assertSame(['awaiting_validation', 'ended'], $created['allowed_transitions']);

        $this->getJson('/api/v1/platform/search-intel/sea/campaigns?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.tracking_mode', 'manual');
    }

    public function test_draft_cannot_go_straight_to_active(): void
    {
        $campaign = $this->makeCampaign();
        $this->actingAsMarketing();

        // Le chemin court est absent de la machine à états, pas seulement de
        // l'interface : un appel direct à l'API est refusé.
        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/transition", [
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_validation_then_activation(): void
    {
        $campaign = $this->makeCampaign();
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/transition", [
            'status' => 'awaiting_validation',
        ])->assertOk()->assertJsonPath('data.status', 'awaiting_validation');

        $validated = $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/validate")
            ->assertOk()
            ->json('data');

        self::assertNotNull($validated['validated_at']);

        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/transition", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.is_spending', true);
    }

    public function test_active_requires_validation(): void
    {
        $campaign = $this->makeCampaign();
        // On force le statut en attente de validation sans passer par
        // `validate()` : l'activation doit rester refusée.
        $campaign->forceFill(['status' => CampaignStatus::AwaitingValidation->value])->save();

        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/transition", [
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_required');
    }

    public function test_metrics_upsert_on_same_day(): void
    {
        $campaign = $this->makeCampaign();
        $this->actingAsMarketing();

        $first = $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/metrics", [
            'measured_on' => '2026-10-20',
            'spend_cents' => 4000,
            'impressions' => 10000,
            'clicks' => 500,
            'conversions' => 10,
        ])->assertStatus(201)->json('data');

        // Même jour → CORRECTION, pas doublon.
        $second = $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/metrics", [
            'measured_on' => '2026-10-20',
            'spend_cents' => 4500,
            'impressions' => 12000,
            'clicks' => 600,
            'conversions' => 12,
        ])->assertStatus(201)->json('data');

        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, $campaign->metrics()->count());

        $show = $this->getJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}")
            ->assertOk()
            ->json();

        self::assertSame(4500, $show['totals']['spend_cents']);
        self::assertSame(12, $show['totals']['conversions']);
        self::assertSame(375, $show['totals']['cost_per_conversion_cents']);
    }

    public function test_integration_metric_requires_integration(): void
    {
        $campaign = $this->makeCampaign();
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/metrics", [
            'measured_on' => '2026-10-20',
            'source' => 'integration',
            'spend_cents' => 100,
            'impressions' => 10,
            'clicks' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'integration_required');
    }

    public function test_no_division_by_zero_on_zero_conversions(): void
    {
        $campaign = $this->makeCampaign();
        $this->actingAsMarketing();

        $metric = $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}/metrics", [
            'measured_on' => '2026-10-21',
            'spend_cents' => 3000,
            'impressions' => 5000,
            'clicks' => 250,
            'conversions' => 0,
        ])->assertStatus(201)->json('data');

        // Pas de CPO infini ni de 0 trompeur : `null` = « pas encore de
        // conversion ».
        self::assertNull($metric['cost_per_conversion_cents']);
        self::assertSame(0.05, $metric['click_through_rate']);
    }

    public function test_comparison_and_budget_alert(): void
    {
        $a = $this->makeCampaign(['name' => 'A', 'total_budget_cents' => 10000]);
        $b = $this->makeCampaign(['name' => 'B', 'total_budget_cents' => 100000]);

        $this->actingAsMarketing();

        // A consomme 95 % de son budget → alerte au seuil de 90 %.
        $response = $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$a->id}/metrics", [
            'measured_on' => '2026-10-20',
            'spend_cents' => 9500,
            'impressions' => 20000,
            'clicks' => 800,
            'conversions' => 20,
        ])->assertStatus(201)->json();

        self::assertSame('budget_alert', $response['budget_alert']['code']);
        self::assertSame(0.95, $response['budget_alert']['consumption_ratio']);

        $this->postJson("/api/v1/platform/search-intel/sea/campaigns/{$b->id}/metrics", [
            'measured_on' => '2026-10-20',
            'spend_cents' => 5000,
            'impressions' => 10000,
            'clicks' => 300,
            'conversions' => 5,
        ])->assertStatus(201);

        $compare = $this->getJson('/api/v1/platform/search-intel/sea/campaigns/compare')
            ->assertOk()
            ->json();

        self::assertCount(2, $compare['data']);
        self::assertTrue($compare['meta']['rankable']);
        self::assertSame('manual', $compare['meta']['tracking_mode']);
    }

    public function test_only_draft_is_editable(): void
    {
        $campaign = $this->makeCampaign();
        $campaign->forceFill(['status' => CampaignStatus::AwaitingValidation->value])->save();

        $this->actingAsMarketing();

        $this->patchJson("/api/v1/platform/search-intel/sea/campaigns/{$campaign->id}", [
            'daily_budget_cents' => 999999,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'campaign_not_editable');
    }
}
