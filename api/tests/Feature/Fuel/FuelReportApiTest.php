<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Reporting opérationnel FuelStation — FUEL-017 (issue #5811).
 *
 * Couvre : snapshot sales pré-agrégé, recalcul idempotent (même clé →
 * pas de doublon), performance station (total, panier moyen), RBAC
 * deny-by-default (pompiste → 403), isolation tenant 404, type inconnu 404.
 */
class FuelReportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/reports/sales?station_id=1')
            ->assertStatus(401);
    }

    public function test_manager_gets_sales_snapshot_and_recompute_is_idempotent(): void
    {
        [$company, $manager, $station] = $this->seedSales();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/reports/sales?station_id='.$station->id.'&period_start='.now()->startOfMonth()->toDateString().'&period_end='.now()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('data.snapshot_type', 'sales')
            ->assertJsonPath('meta.recomputed', true)
            ->assertJsonPath('data.payload.totals.sale_count', 2);

        $this->assertEqualsWithDelta(450.0, (float) $this->getJson('/api/v1/fuel-station/reports/sales?station_id='.$station->id.'&period_start='.now()->startOfMonth()->toDateString().'&period_end='.now()->toDateString())->json('data.payload.totals.amount_total'), 0.01);

        $this->assertSame(1, FuelReportSnapshot::query()->count());

        // Rejeu : snapshot existant, pas de doublon.
        $this->getJson('/api/v1/fuel-station/reports/sales?station_id='.$station->id.'&period_start='.now()->startOfMonth()->toDateString().'&period_end='.now()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('meta.recomputed', false);

        $this->assertSame(1, FuelReportSnapshot::query()->count());
    }

    public function test_manager_gets_station_performance(): void
    {
        [$company, $manager, $station] = $this->seedSales();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/reports/station_performance?station_id='.$station->id.'&period_start='.now()->startOfMonth()->toDateString().'&period_end='.now()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('data.payload.station_id', $station->id)
            ->assertJsonPath('data.payload.total_sales_count', 2);

        $payload = $this->getJson('/api/v1/fuel-station/reports/station_performance?station_id='.$station->id.'&period_start='.now()->startOfMonth()->toDateString().'&period_end='.now()->toDateString())->json('data.payload');
        $this->assertEqualsWithDelta(450.0, (float) ($payload['total_revenue'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(225.0, (float) ($payload['average_basket'] ?? 0), 0.01);
    }

    public function test_operator_gets_403_on_reports(): void
    {
        [$company, $manager, $station] = $this->seedSales();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/reports/sales?station_id='.$station->id)
            ->assertStatus(403);
    }

    public function test_cross_tenant_station_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = FuelStation::query()->create([
            'company_id' => $companyB->id,
            'code' => 'ST-B',
            'name' => 'Station B',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson('/api/v1/fuel-station/reports/sales?station_id='.$stationB->id)
            ->assertStatus(404);
    }

    public function test_unknown_type_is_404(): void
    {
        [$company, $manager, $station] = $this->seedSales();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/reports/nonsense?station_id='.$station->id)
            ->assertStatus(404);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: FuelStation}
     */
    private function seedSales(): array
    {
        [$company, $manager] = $this->seedTenant();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'essence',
            'quantity' => 1.0,
            'unit_price' => 150.0,
            'amount' => 150.0,
            'sale_time' => now(),
            'source' => FuelSale::SOURCE_MANUAL,
        ]);
        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'diesel',
            'quantity' => 2.0,
            'unit_price' => 150.0,
            'amount' => 300.0,
            'sale_time' => now(),
            'source' => FuelSale::SOURCE_MANUAL,
        ]);

        return [$company, $manager, $station];
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }
}
