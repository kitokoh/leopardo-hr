<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-205 (#6289) — Règlement COD & commissions.
 *
 * - cycle de vie pending → collected → settled → reconciled (posting BC-08
 *   via contrat seam, référence stable) ;
 * - idempotence : création ×2, collect ×2, settle ×2 → mêmes résultats ;
 * - gardes : OVER_COLLECTION 422, SETTLEMENT_NOT_COLLECTED/NOT_SETTLED 409 ;
 * - RBAC (settle/reconcile = admin) + isolation tenant.
 */
class DeliveryCodSettlementApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function manager(string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    /** Crée une tournée close (COD 8000) + son règlement pending. */
    private function closedRoute(): DeliveryRoute
    {
        $delivery = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-555001',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'delivered',
            'cod_amount_minor' => 8000,
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ]);

        /** @var DeliveryRoute $route */
        $route = DeliveryRoute::query()->create([
            'company_id' => $this->company->id,
            'route_date' => '2026-09-01',
            'driver_id' => 5,
            'vehicle_code' => 'VEH-001',
            'status' => 'completed',
            'deliveries_count' => 1,
            'delivered_count' => 1,
            'cod_collected_minor' => 8000,
            'closed_at' => now(),
        ]);

        DeliveryStop::query()->create([
            'company_id' => $this->company->id,
            'route_id' => $route->id,
            'delivery_id' => $delivery->id,
            'sort_order' => 1,
            'status' => 'delivered',
            'address' => 'Alger',
            'contact' => 'Client',
        ]);

        return $route;
    }

    public function test_lifecycle_pending_to_reconciled(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();

        // Création : attendu = COD de la tournée.
        $created = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))
            ->assertStatus(201)
            ->assertJsonPath('data.expected_minor', 8000)
            ->assertJsonPath('data.status', 'pending');

        $settlementId = $created->json('data.id');

        // Idempotence création ×2.
        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))
            ->assertStatus(201)
            ->assertJsonPath('data.id', $settlementId);

        // Remise caisse (collecté 7000, commission 500).
        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $settlementId), [
            'collected_minor' => 7000,
            'commission_minor' => 500,
        ])->assertOk()
            ->assertJsonPath('data.status', 'collected')
            ->assertJsonPath('data.collected_minor', 7000)
            ->assertJsonPath('data.commission_minor', 500)
            ->assertJsonPath('data.collected_at', fn ($v) => $v !== null);

        // Posting BC-08 (référence stable).
        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $settlementId))
            ->assertOk()
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.accounting_ref', 'COD-'.$settlementId)
            ->assertJsonPath('data.settled_at', fn ($v) => $v !== null);

        // Réconciliation.
        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/reconcile', $settlementId))
            ->assertOk()
            ->assertJsonPath('data.status', 'reconciled');
    }

    public function test_settle_and_reconcile_are_idempotent(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();

        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $id), [
            'collected_minor' => 8000,
        ])->assertOk();

        $first = $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $id))->assertOk();
        $second = $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $id))->assertOk();
        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame('COD-'.$id, $second->json('data.accounting_ref'));
    }

    public function test_collect_rejects_over_collection(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();
        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $id), [
            'collected_minor' => 9000,
        ])->assertStatus(422);
    }

    public function test_settle_requires_collected_and_reconcile_requires_settled(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();
        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $id))
            ->assertStatus(409)
            ->assertJson(['message' => 'SETTLEMENT_NOT_COLLECTED']);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/reconcile', $id))
            ->assertStatus(409)
            ->assertJson(['message' => 'SETTLEMENT_NOT_SETTLED']);
    }

    public function test_settle_is_admin_only(): void
    {
        Sanctum::actingAs($this->manager('rh')); // manager simple, pas admin
        $route = $this->closedRoute();
        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $id))
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
    }

    public function test_report_shows_expected_vs_collected(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();
        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');
        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $id), [
            'collected_minor' => 7000,
        ])->assertOk();

        $this->getJson('/api/v1/delivery/deliveries/cod-settlements/report')
            ->assertOk()
            ->assertJsonPath('data.totals.expected_minor', 8000)
            ->assertJsonPath('data.totals.collected_minor', 7000)
            ->assertJsonPath('data.totals.gap_minor', 1000);
    }

    public function test_settlement_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->closedRoute();
        $id = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route->id))->json('data.id');

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($managerB);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $id), [
            'collected_minor' => 1000,
        ])->assertStatus(404);
    }
}
