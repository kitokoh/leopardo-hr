<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * Couvre : livraison idempotente (external_id), montant en unités mineures,
 * rejet quantité non positive, cuve hors tenant refusée (404), RBAC manager
 * (403 pompiste), rapprochement rejouable (unique par station/date), écart
 * rapporté sans ajustement silencieux, isolation tenant 404.
 */
class FuelStockApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/tanks/1/deliveries', [
            'quantity_minor' => 1000,
        ])->assertStatus(401);
    }

    public function test_manager_records_idempotent_delivery(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $payload = [
            'quantity_minor' => 5000,
            'unit_price_minor' => 12000,
            'external_id' => 'delivery-2026-08-30-001',
            'notes' => 'Livraison hebdo',
        ];

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.quantity_minor', 5000)
            ->assertJsonPath('data.tank_id', $tank->id)
            ->assertJsonPath('data.external_id', 'delivery-2026-08-30-001');

        $this->assertDatabaseHas('fuel_tank_deliveries', [
            'company_id' => $company->id,
            'tank_id' => $tank->id,
            'quantity_minor' => 5000,
        ]);

        // Le niveau courant de la cuve est incrémenté (mouvement légitime).
        $this->assertSame(15000, (int) $tank->refresh()->current_level_minor);

        // Rejeu idempotent : même external_id → même livraison, pas de doublon.
        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.id', FuelTankDelivery::query()->firstOrFail()->id);

        $this->assertSame(1, FuelTankDelivery::query()->count());
        $this->assertSame(15000, (int) $tank->refresh()->current_level_minor);
    }

    public function test_delivery_rejects_non_positive_quantity(): void
    {
        [, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => 0,
        ])->assertStatus(422);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => -10,
        ])->assertStatus(422);
    }

    public function test_operator_cannot_record_delivery(): void
    {
        [, , $operator, $tank] = $this->seedTenant();

        Sanctum::actingAs($operator);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => 1000,
        ])->assertStatus(403);
    }

    public function test_cross_tenant_delivery_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);
        $tankB = $this->createTank($companyB, $stationB);

        Sanctum::actingAs($managerA);

        // Le tank appartient au tenant B : le route model binding résout le
        // modèle global, puis le contrôleur refuse (tenant mismatch → 404).
        $this->postJson("/api/v1/fuel-station/tanks/{$tankB->id}/deliveries", [
            'quantity_minor' => 1000,
        ])->assertStatus(404);

        $this->assertSame(0, FuelTankDelivery::query()->where('company_id', $companyA->id)->count());
    }

    public function test_manager_lists_stock_levels_with_fill_ratio(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stocks')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $tank->id)
            ->assertJsonPath('data.0.current_level_minor', 10000)
            ->assertJsonPath('data.0.fill_ratio', 0.5);
    }

    public function test_reconciliation_is_replayable_and_reports_variance(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        // Vente du même produit que la cuve, via l'API (FuelSaleService) :
        // le niveau de la cuve est décrémenté (10000 → 8000).
        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $tank->station_id,
            'product' => $tank->product_type,
            'quantity' => 2.0,
            'unit_price' => 150.0,
        ])->assertStatus(200);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('meta.replayed', false);

        $this->assertSame(1, FuelReconciliationRun::query()->count());

        // Rejeu : même (station, date) → run existant, aucun recalcul.
        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('meta.replayed', true);

        $this->assertSame(1, FuelReconciliationRun::query()->count());

        $run = FuelReconciliationRun::query()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertIsArray($run->summary);

        $summary = $run->summary;
        $this->assertArrayHasKey('tanks', $summary);

        $tankLine = collect($summary['tanks'])->firstWhere('tank_id', $tank->id);
        $this->assertNotNull($tankLine);

        // La vente de 2 L (2000 unités mineures) a décrémenté la cuve
        // (FUEL-009) : niveau 10000 → 8000. Ouverture dérivée = 8000 + 2000
        // = 10000 ; attendu = 10000 − 2000 = 8000 = mesuré → écart 0.
        $this->assertSame(10000, $tankLine['opening_level_minor']);
        $this->assertSame(0, $tankLine['deliveries_minor']);
        $this->assertSame(2000, $tankLine['sales_minor']);
        $this->assertSame(8000, $tankLine['expected_level_minor']);
        $this->assertSame(8000, $tankLine['measured_level_minor']);
        $this->assertSame(0, $tankLine['variance_minor']);
        $this->assertTrue($tankLine['explainable']);
    }

    public function test_reconciliation_reports_unexplained_variance(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        // Vente de 2 L via l'API : la cuve passe de 10000 à 8000 (décrément).
        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $tank->station_id,
            'product' => $tank->product_type,
            'quantity' => 2.0,
            'unit_price' => 150.0,
        ])->assertStatus(200);

        // Simule une perte non enregistrée (vol/fuite) : le niveau physique
        // est plus bas que ce que le registre (ventes) justifie.
        $tank->update(['current_level_minor' => 6000]);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])->assertStatus(200);

        $run = FuelReconciliationRun::query()->firstOrFail();
        $tankLine = collect($run->summary['tanks'])->firstWhere('tank_id', $tank->id);

        // Registre : ouverture 10000 − ventes 2000 = attendu 8000 ; mesuré
        // physique 6000 → écart +2000, NON expliqué (aucun ajustement).
        $this->assertSame(8000, $tankLine['expected_level_minor']);
        $this->assertSame(6000, $tankLine['measured_level_minor']);
        $this->assertSame(2000, $tankLine['variance_minor']);
        $this->assertFalse($tankLine['explainable']);
    }

    public function test_reconciliation_relaunches_failed_run(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        $failed = FuelReconciliationRun::query()->create([
            'company_id' => $company->id,
            'station_id' => $tank->station_id,
            'run_date' => now()->toDateString(),
            'status' => FuelReconciliationRun::STATUS_FAILED,
            'last_error' => 'boom',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.id', $failed->id);

        $this->assertSame(1, FuelReconciliationRun::query()->count());
    }

    public function test_reconciliation_lists_and_shows(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations")
            ->assertStatus(200);

        $run = FuelReconciliationRun::query()->firstOrFail();

        $this->getJson('/api/v1/fuel-station/reconciliations')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $run->id);

        $this->getJson("/api/v1/fuel-station/reconciliations/{$run->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_cross_tenant_reconciliation_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);

        Sanctum::actingAs($managerA);

        $this->postJson("/api/v1/fuel-station/stations/{$stationB->id}/reconciliations")
            ->assertStatus(404);
    }

    public function test_solution_inactive_returns_403(): void
    {
        $company = Company::factory()->create(['features' => []]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stocks')->assertStatus(403);
    }

    private function createStation(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    private function createTank(Company $company, FuelStation $station): FuelTank
    {
        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'TNK-01',
            'product_type' => 'essence',
            'capacity_minor' => 20000,
            'current_level_minor' => 10000,
            'status' => FuelTank::STATUS_ACTIVE,
        ]);

        return $tank;
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: FuelTank}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = $this->createStation($company);
        $tank = $this->createTank($company, $station);

        return [$company, $manager, $operator, $tank];
    }
}
