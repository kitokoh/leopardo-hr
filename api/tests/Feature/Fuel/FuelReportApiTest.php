<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
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
        $this->assertArrayHasKey('top_products', $data);
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
}
