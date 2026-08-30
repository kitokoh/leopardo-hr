<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockDelivery;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankStockLevel;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * Couvre : auth 401, RBAC manager (403 pompiste), niveau de cuve avec
 * rejeu idempotent (idempotency_key), livraison avec rejeu par référence,
 * réception idempotente + dépassement de capacité refusé, rapprochement
 * rejouable (même (station, jour) → même rapport, recalculé), écart
 * explicable (explication requise si variance ≠ 0), isolation tenant 404,
 * solution inactive 403.
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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    /** @return array{FuelStation, FuelTank} */
    private function fixture(Company $company, int $capacityMinor = 100000): array
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-FUEL-'.random_int(100, 999),
            'name' => 'Station test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'TK-01',
            'product_type' => 'essence',
            'capacity_minor' => $capacityMinor,
            'current_level_minor' => 0,
            'status' => FuelTank::STATUS_ACTIVE,
        ]);

        return [$station, $tank];
    }

    private function recordLevel(Employee $actor, FuelTank $tank, int $levelMinor, string $key): TestResponse
    {
        return $this->actingAs($actor)->postJson('/api/v1/fuel-station/tanks/'.(int) $tank->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => $levelMinor,
            'source_code' => 'manual',
            'idempotency_key' => $key,
        ]);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stock-deliveries')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/reconciliations', [])->assertStatus(401);
    }

    public function test_operator_cannot_manage_stock(): void
    {
        [$station, $tank] = $this->fixture($this->companyA);
        $operator = $this->operator($this->companyA);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/tanks/'.(int) $tank->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => 5000,
        ])->assertStatus(403);

        $this->getJson('/api/v1/fuel-station/stock-deliveries')->assertStatus(403);

        $this->getJson('/api/v1/fuel-station/reconciliations')->assertStatus(403);
    }

    public function test_manager_records_stock_level_with_idempotent_replay(): void
    {
        [$station, $tank] = $this->fixture($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $first = $this->postJson('/api/v1/fuel-station/tanks/'.(int) $tank->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => 5000,
            'idempotency_key' => 'level-2026-08-30-01',
        ])->assertStatus(200)->assertJsonPath('data.level_minor', 5000);

        $id = (int) $first->json('data.id');

        // Rejeu : même clé → même niveau (zéro doublon).
        $this->postJson('/api/v1/fuel-station/tanks/'.(int) $tank->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => 9999,
            'idempotency_key' => 'level-2026-08-30-01',
        ])->assertStatus(200)->assertJsonPath('data.id', $id)->assertJsonPath('data.level_minor', 5000);

        // Niveau négatif refusé par la validation.
        $this->postJson('/api/v1/fuel-station/tanks/'.(int) $tank->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => -10,
        ])->assertStatus(422);
    }

    public function test_tank_of_another_tenant_is_rejected(): void
    {
        [, $tankB] = $this->fixture($this->companyB);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/tanks/'.(int) $tankB->getAttribute('id').'/stock-levels', [
            'tank_id' => (int) $tankB->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => 5000,
        ])->assertStatus(422);
    }

    public function test_delivery_replay_by_reference_and_idempotent_receive(): void
    {
        [$station, $tank] = $this->fixture($this->companyA, 10000);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $first = $this->postJson('/api/v1/fuel-station/stock-deliveries', [
            'station_id' => (int) $station->getAttribute('id'),
            'tank_id' => (int) $tank->getAttribute('id'),
            'product_code' => 'essence',
            'supplier_name' => 'Fournisseur X',
            'quantity_minor' => 6000,
            'unit_code' => 'l',
            'reference' => 'BL-2026-08-30-01',
        ])->assertStatus(200)->assertJsonPath('data.status', 'draft');

        $id = (int) $first->json('data.id');

        // Rejeu même référence → même livraison (zéro doublon).
        $this->postJson('/api/v1/fuel-station/stock-deliveries', [
            'station_id' => (int) $station->getAttribute('id'),
            'tank_id' => (int) $tank->getAttribute('id'),
            'product_code' => 'essence',
            'quantity_minor' => 6000,
            'reference' => 'BL-2026-08-30-01',
        ])->assertStatus(200)->assertJsonPath('data.id', $id);

        // Réception → status received + niveau de cuve incrémenté.
        $this->postJson('/api/v1/fuel-station/stock-deliveries/'.$id.'/receive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        $tank->refresh();
        $this->assertSame(6000, $tank->current_level_minor);

        // Rejeu de réception → état inchangé.
        $this->postJson('/api/v1/fuel-station/stock-deliveries/'.$id.'/receive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        $tank->refresh();
        $this->assertSame(6000, $tank->current_level_minor);
    }

    public function test_receive_above_capacity_is_rejected(): void
    {
        [$station, $tank] = $this->fixture($this->companyA, 10000);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $delivery = $this->postJson('/api/v1/fuel-station/stock-deliveries', [
            'station_id' => (int) $station->getAttribute('id'),
            'tank_id' => (int) $tank->getAttribute('id'),
            'product_code' => 'essence',
            'quantity_minor' => 12000,
            'reference' => 'BL-2026-08-30-OVER',
        ])->assertStatus(200)->json('data');

        $this->postJson('/api/v1/fuel-station/stock-deliveries/'.(int) $delivery['id'].'/receive')
            ->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_TANK_CAPACITY_EXCEEDED');
    }

    public function test_reconciliation_is_replayable_and_explains_variance(): void
    {
        [$station, $tank] = $this->fixture($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        // Contexte : ouverture 3000 (niveau antérieur), livraison reçue 2000,
        // ventes 25.5 l → 2550 unités mineures, clôture enregistrée 2600.
        FuelTankStockLevel::query()->create([
            'company_id' => $this->companyA->id,
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-29',
            'level_minor' => 3000,
            'source_code' => 'manual',
        ]);

        FuelStockDelivery::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'tank_id' => (int) $tank->getAttribute('id'),
            'product_code' => 'essence',
            'quantity_minor' => 2000,
            'unit_code' => 'l',
            'delivered_at' => '2026-08-30 08:00:00',
            'reference' => 'BL-REC-01',
            'status' => FuelStockDelivery::STATUS_RECEIVED,
            'received_at' => '2026-08-30 08:05:00',
        ]);

        FuelSale::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'employee_id' => (int) $manager->getAttribute('id'),
            'product' => 'Essence sans plomb',
            'quantity' => 25.5,
            'unit_price' => 150.0,
            'amount' => 3825.0,
            'sale_time' => '2026-08-30 10:00:00',
        ]);

        FuelTankStockLevel::query()->create([
            'company_id' => $this->companyA->id,
            'tank_id' => (int) $tank->getAttribute('id'),
            'recorded_on' => '2026-08-30',
            'level_minor' => 2600,
            'source_code' => 'manual',
        ]);

        $first = $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => (int) $station->getAttribute('id'),
            'report_date' => '2026-08-30',
        ])->assertStatus(200);

        // expected = 3000 + 2000 − 2550 = 2450 ; closing 2600 → variance +150.
        $first->assertJsonPath('data.opening_stock_minor', 3000)
            ->assertJsonPath('data.deliveries_minor', 2000)
            ->assertJsonPath('data.sales_minor', 2550)
            ->assertJsonPath('data.expected_stock_minor', 2450)
            ->assertJsonPath('data.closing_stock_minor', 2600)
            ->assertJsonPath('data.variance_minor', 150)
            ->assertJsonPath('data.status', 'pending_review');

        $reportId = (int) $first->json('data.id');

        // Rejeu : même (station, jour) → MÊME rapport, recalculé (zéro doublon).
        $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => (int) $station->getAttribute('id'),
            'report_date' => '2026-08-30',
        ])->assertStatus(200)->assertJsonPath('data.id', $reportId);

        // Revue sans explication refusée (écart ≠ 0 → explication obligatoire).
        $this->postJson('/api/v1/fuel-station/reconciliations/'.$reportId.'/review', [
            'status' => 'reviewed',
        ])->assertStatus(422)->assertJsonPath('error', 'FUEL_RECONCILIATION_EXPLANATION_REQUIRED');

        // Revue avec explication → reviewed.
        $this->postJson('/api/v1/fuel-station/reconciliations/'.$reportId.'/review', [
            'status' => 'reviewed',
            'explanation' => 'Écart dû à un relevé intermédiaire non saisi (gestion manuelle).',
        ])->assertStatus(200)->assertJsonPath('data.status', 'reviewed');
    }

    public function test_cross_tenant_delivery_receive_is_404(): void
    {
        [$stationB] = $this->fixture($this->companyB);
        $managerB = $this->manager($this->companyB);
        Sanctum::actingAs($managerB);

        $delivery = FuelStockDelivery::query()->create([
            'company_id' => $this->companyB->id,
            'station_id' => (int) $stationB->getAttribute('id'),
            'product_code' => 'essence',
            'quantity_minor' => 1000,
            'reference' => 'BL-CROSS-01',
            'status' => FuelStockDelivery::STATUS_DRAFT,
        ]);

        $managerA = $this->manager($this->companyA);
        Sanctum::actingAs($managerA);

        $this->postJson('/api/v1/fuel-station/stock-deliveries/'.(int) $delivery->getAttribute('id').'/receive')
            ->assertStatus(404);
    }

    public function test_solution_inactive_returns_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'features' => [],
        ]);
        $manager = $this->manager($inactive);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stock-deliveries')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FUEL_SOLUTION_INACTIVE');
    }
}
