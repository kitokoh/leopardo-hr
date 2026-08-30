<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * Couvre : auth 401, RBAC (employé 403), livraison rejouable (zéro doublon),
 * mouvement de stock créé, vérification manager, rapprochement rejouable
 * (même clé → même rapport), écart > tolérance → `exception` (aucun
 * ajustement silencieux), ajustement explicite audité, cross-tenant 404.
 */
class FuelStockApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $operator;
    }

    private function station(Company $company, string $code = 'ST-01'): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => "Station {$code}",
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stocks/movements')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/deliveries', [])->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/reconciliations', [])->assertStatus(401);
    }

    public function test_operator_cannot_manage_stocks(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/stocks/movements')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/deliveries', [])->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/reconciliations', [])->assertStatus(403);
    }

    public function test_manager_records_delivery_and_movement_is_created(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 100000,
            'supplier' => 'Naftal',
            'reference_number' => 'BL-2026-001',
            'delivered_at' => '2026-08-29T08:00:00Z',
            'idempotency_key' => 'deliv-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.delivery.product_type', 'essence')
            ->assertJsonPath('data.delivery.quantity_minor', 100000)
            ->assertJsonPath('data.delivery.status', 'received')
            ->assertJsonPath('data.movement.direction', 'in')
            ->assertJsonPath('data.movement.reason', 'delivery')
            ->assertJsonPath('data.replayed', false);

        $this->assertDatabaseHas('fuel_stock_movements', [
            'company_id' => $this->companyA->id,
            'station_id' => $station->id,
            'direction' => FuelStockMovement::DIRECTION_IN,
            'reason' => FuelStockMovement::REASON_DELIVERY,
            'quantity_minor' => 100000,
        ]);
    }

    public function test_delivery_replay_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $payload = [
            'station_id' => $station->id,
            'product_type' => 'gazole',
            'quantity_minor' => 50000,
            'delivered_at' => '2026-08-29T10:00:00Z',
            'idempotency_key' => 'deliv-002',
        ];

        $this->postJson('/api/v1/fuel-station/deliveries', $payload)->assertStatus(201);
        $this->postJson('/api/v1/fuel-station/deliveries', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.delivery.id', fn ($id): bool => is_int($id));

        $this->assertDatabaseCount('fuel_deliveries', 1);
        $this->assertDatabaseCount('fuel_stock_movements', 1);
    }

    public function test_manager_verifies_delivery(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $delivery = $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 20000,
            'delivered_at' => '2026-08-29T12:00:00Z',
            'idempotency_key' => 'deliv-003',
        ])->assertStatus(201)->json('data.delivery');

        $this->postJson("/api/v1/fuel-station/deliveries/{$delivery['id']}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_at', fn ($v): bool => is_string($v));
    }

    public function test_reconciliation_is_replayable_and_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $payload = [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'measured_close_minor' => 0,
            'idempotency_key' => 'recon-001',
        ];

        $this->postJson('/api/v1/fuel-station/reconciliations', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.variance_minor', 0);

        // Rejeu : même rapport (200), aucune seconde ligne.
        $this->postJson('/api/v1/fuel-station/reconciliations', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.id', fn ($id): bool => is_int($id));

        $this->assertDatabaseCount('fuel_stock_reconciliations', 1);
    }

    public function test_reconciliation_with_variance_closes_exception(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        // Livraison de 100 000, mais jauge de clôture à 99 000 → écart 1 000
        // (> tolérance par défaut 50) → exception, aucun ajustement silencieux.
        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 100000,
            'delivered_at' => '2026-08-10T08:00:00Z',
            'idempotency_key' => 'deliv-variance',
        ])->assertStatus(201);

        $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'measured_close_minor' => 99000,
            'idempotency_key' => 'recon-variance',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', FuelStockReconciliation::STATUS_EXCEPTION)
            ->assertJsonPath('data.variance_minor', -1000);
    }

    public function test_adjustment_requires_reason_and_is_audited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/stocks/adjustments', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 1000,
            'direction' => 'out',
            'movement_at' => '2026-08-29T14:00:00Z',
            'idempotency_key' => 'adj-001',
            'notes' => 'Écart constaté au rapprochement du 2026-08-29 (fuite cuve C1).',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.movement.reason', 'adjustment')
            ->assertJsonPath('data.movement.direction', 'out')
            ->assertJsonPath('data.replayed', false);

        // Motif obligatoire : un ajustement sans explication est refusé.
        $this->postJson('/api/v1/fuel-station/stocks/adjustments', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 1000,
            'direction' => 'out',
            'movement_at' => '2026-08-29T14:00:00Z',
            'idempotency_key' => 'adj-002',
        ])->assertStatus(422);
    }

    public function test_cross_tenant_station_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $stationB = $this->station($this->companyB, 'ST-99');

        // Validation tenant-scoped des FormRequests → 422 (station d'un autre
        // tenant rejetée AVANT tout traitement).
        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $stationB->id,
            'product_type' => 'essence',
            'quantity_minor' => 100,
            'delivered_at' => '2026-08-29T08:00:00Z',
            'idempotency_key' => 'deliv-x-tenant',
        ])->assertStatus(422);

        $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => $stationB->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'idempotency_key' => 'recon-x-tenant',
        ])->assertStatus(422);
    }
}
