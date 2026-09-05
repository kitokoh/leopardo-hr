<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Reporting opérationnel — FUEL-017 (issue #5811).
 *
 * Couvre : ventes journalières (total, volume, panier moyen, top produits),
 * synthèse de shift, anomalies de compteur, deny-by-default (403 opérateur).
 */
class FuelReportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    public function test_operator_cannot_access_reports(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->getJson('/api/v1/fuel-station/reports/daily-sales')->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/reports/anomalies')->assertStatus(403);
    }

    public function test_daily_sales_report_aggregates(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        FuelSale::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'product' => 'Essence',
            'quantity' => 20,
            'unit_price' => 150,
            'amount' => 3000,
            'sale_time' => now(),
            'source' => 'manual',
        ]);

        FuelSale::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'product' => 'Gazole',
            'quantity' => 10,
            'unit_price' => 140,
            'amount' => 1400,
            'sale_time' => now(),
            'source' => 'manual',
        ]);

        $this->getJson('/api/v1/fuel-station/reports/daily-sales')
            ->assertStatus(200)
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.total_amount', 4400)
            ->assertJsonPath('data.average_basket', 2200);

        $data = $this->getJson('/api/v1/fuel-station/reports/daily-sales')->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('top_products', $data);
        $this->assertIsArray($data['top_products']);
        $this->assertArrayHasKey('Essence', $data['top_products']);
    }

    public function test_shift_summary_returns_snapshot(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $data = $this->getJson('/api/v1/fuel-station/reports/shift-summary?shift_id='.$shift->id)
            ->assertStatus(200)
            ->assertJsonPath('data.shift_id', $shift->id)
            ->json('data');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('sales_count', $data);
        $this->assertArrayHasKey('assignments_count', $data);
    }

    public function test_anomalies_report_manager_only(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/v1/fuel-station/reports/anomalies?date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

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
