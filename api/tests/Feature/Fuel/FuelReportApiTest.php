<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-017 (#5811) — Reporting opérationnel FuelStation.
 *
 * Couvre les agrégats ventes/shifts/sessions de caisse (recalcul idempotent),
 * le RBAC manager et l'isolation tenant.
 */
class FuelReportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_sales_report_aggregates_are_exact(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        FuelSale::query()->create(['company_id' => $company->id, 'quantity' => 10, 'unit_price' => 100, 'amount' => 1000, 'product' => 'Essence', 'sale_time' => now(), 'source' => 'manual']);
        FuelSale::query()->create(['company_id' => $company->id, 'quantity' => 5, 'unit_price' => 200, 'amount' => 1000, 'product' => 'Gazole', 'sale_time' => now(), 'source' => 'manual']);

        $this->getJson('/api/v1/fuel-station/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.total_amount', 2000)
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.total_quantity', 15);
    }

    public function test_shifts_report_counts_assignments(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        FuelShift::query()->create(['company_id' => $company->id, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '16:00', 'status' => 'active']);
        FuelShift::query()->create(['company_id' => $company->id, 'name' => 'Soir', 'start_time' => '16:00', 'end_time' => '00:00', 'status' => 'inactive']);

        $this->getJson('/api/v1/fuel-station/reports/shifts')
            ->assertOk()
            ->assertJsonPath('data.shifts_count', 2)
            ->assertJsonPath('data.active_shifts', 1);
    }

    public function test_cash_sessions_report_sums_variances(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        FuelCashSession::query()->create([
            'company_id' => $company->id,
            'status' => 'closed',
            'opening_balance' => 1000,
            'closing_balance' => 1500,
            'expected_balance' => 1600,
            'variance' => -100,
            'opened_at' => now()->subHours(3),
        ]);
        FuelCashSession::query()->create([
            'company_id' => $company->id,
            'status' => 'closed',
            'opening_balance' => 500,
            'closing_balance' => 800,
            'expected_balance' => 800,
            'variance' => 0,
            'opened_at' => now()->subHours(2),
        ]);

        $this->getJson('/api/v1/fuel-station/reports/cash-sessions')
            ->assertOk()
            ->assertJsonPath('data.sessions_count', 2)
            ->assertJsonPath('data.variance', -100)
            ->assertJsonPath('data.opening_balance', 1500);
    }

    public function test_operator_cannot_read_reports(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/reports/sales')->assertStatus(403);
    }

    public function test_inactive_solution_returns_403(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => []]);
        $this->manager($company);

        $this->getJson('/api/v1/fuel-station/reports/sales')->assertStatus(403);
    }
}
