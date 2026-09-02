<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D03 (#6293) — Isolation tenant & tests cross-tenant DeliveryAgency.
 *
 * Matrice : pour CHAQUE endpoint du module, un manager du tenant B ne doit
 * jamais voir ni toucher les données du tenant A (404 / liste vide) —
 * fail-closed #3727.
 */
class DeliveryTenantIsolationMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $managerB;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $a */
        $a = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $a->setFeature('delivery', true);
        $a->save();
        $this->companyA = $a;

        /** @var Company $b */
        $b = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $b->setFeature('delivery', true);
        $b->save();
        $this->companyB = $b;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'id' => 71,
            'company_id' => $a->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'id' => 72,
            'company_id' => $b->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->managerB = $managerB;
        $this->managerA = $managerA;

        // Seed tenant A (managerA).
        Sanctum::actingAs($managerA);

        $delivery = Delivery::query()->create([
            'company_id' => $a->id,
            'reference' => 'DLV-2026-333001',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'assigned',
            'cod_amount_minor' => 5000,
            'dropoff_contact' => 'Client A',
            'dropoff_phone' => '+21355500001',
            'dropoff_address' => 'Alger',
        ]);

        $route = DeliveryRoute::query()->create([
            'company_id' => $a->id,
            'route_date' => now()->toDateString(),
            'driver_id' => 71,
            'status' => 'assigned',
            'cod_collected_minor' => 5000,
        ]);

        $stop = DeliveryStop::query()->create([
            'company_id' => $a->id,
            'route_id' => $route->id,
            'delivery_id' => $delivery->id,
            'sort_order' => 1,
            'status' => 'pending',
            'address' => 'Alger',
        ]);

        DeliveryCodSettlement::query()->create([
            'company_id' => $a->id,
            'route_id' => $route->id,
            'driver_id' => 71,
            'expected_minor' => 5000,
            'collected_minor' => 0,
            'status' => 'pending',
        ]);

        DeliveryNotification::query()->create([
            'company_id' => $a->id,
            'delivery_id' => $delivery->id,
            'event_type' => 'picked_up',
            'channel' => 'whatsapp',
            'recipient_phone' => '+21355500001',
            'template_key' => 'delivery.in_transit',
            'status' => 'pending',
        ]);

        $this->ids = [
            'delivery' => (int) $delivery->id,
            'route' => (int) $route->id,
            'stop' => (int) $stop->id,
            'settlement' => (int) DeliveryCodSettlement::query()->firstOrFail()->id,
        ];
    }

    public function test_manager_b_never_see_tenant_a_data(): void
    {
        Sanctum::actingAs($this->managerB);

        // Listes : vides.
        $this->getJson('/api/v1/delivery/deliveries')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/delivery/deliveries/cod-settlements')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/delivery/deliveries/cod-settlements/report')
            ->assertOk()
            ->assertJsonPath('data.totals.expected_minor', 0);
        $this->getJson('/api/v1/delivery/deliveries/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.deliveries', 0);

        // Lecture par id : 404 fail-closed.
        $this->getJson(sprintf('/api/v1/delivery/deliveries/%d', $this->ids['delivery']))->assertStatus(404);
        $this->getJson(sprintf('/api/v1/delivery/deliveries/routes/%d', $this->ids['route']))->assertStatus(404);
        $this->getJson(sprintf('/api/v1/delivery/deliveries/%d/tracking', $this->ids['delivery']))->assertStatus(404);

        // Écritures : 404 (ressource inconnue) — jamais 403/200.
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $this->ids['delivery'],
            'type' => 'picked_up',
        ])->assertStatus(404);
        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $this->ids['route']), [
            'driver_id' => 72,
        ])->assertStatus(404);
        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/close', $this->ids['route']))->assertStatus(404);
        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $this->ids['stop']), [
            'status' => 'arrived',
        ])->assertStatus(404);
        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $this->ids['settlement']), [
            'collected_minor' => 1000,
        ])->assertStatus(404);
        $this->postJson(sprintf('/api/v1/delivery/deliveries/%d/tracking-link', $this->ids['delivery']))->assertStatus(404);
    }

    public function test_route_creation_cannot_use_foreign_deliveries(): void
    {
        Sanctum::actingAs($this->managerB);

        // Le colis du tenant A ne peut pas être planifié par le tenant B.
        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => now()->toDateString(),
            'delivery_ids' => [$this->ids['delivery']],
        ])->assertStatus(422);
    }

    public function test_public_tracking_link_resolves_its_own_tenant(): void
    {
        Sanctum::actingAs($this->managerB);

        // Le lien public (token = credential) résout la livraison du tenant A
        // par DESIGN — le token EST l'autorisation (pattern #5225) : ce test
        // verrouille le comportement et l'absence de fuite de l'URL.
        $link = $this->postJson(sprintf('/api/v1/delivery/deliveries/%d/tracking-link', $this->ids['delivery']))
            ->assertStatus(404); // le manager B ne peut PAS générer de lien sur A

        // Un lien généré par le tenant A reste résolvable publiquement.
        Sanctum::actingAs($this->managerA);
        $share = \App\Modules\Delivery\Domain\Models\DeliveryTrackingShare::query()->create([
            'company_id' => $this->companyA->id,
            'delivery_id' => $this->ids['delivery'],
            'share_token' => str_repeat('c', 64),
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/deliveries/tracking/'.$share->share_token)
            ->assertOk()
            ->assertJsonPath('data.reference', 'DLV-2026-333001');
    }
}
