<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PA2-PAY-017 — accounting CSV export must carry currency, country, and pay
 * period alongside amounts, so the downloaded file is self-describing for an
 * accountant working across several tenants/countries.
 */
class PayrollAccountingExportTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    public function test_export_csv_includes_currency_country_and_period(): void
    {
        $company = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'matricule' => 'EMP-042',
        ]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'country_code' => 'MA',
            'status' => 'validated',
            'total_gross' => 5000,
            'total_deductions' => 500,
            'total_net' => 4500,
            'total_employer_cost' => 5800,
            'employee_count' => 1,
        ]);

        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'gross_salary' => 5000,
            'total_deductions' => 500,
            'net_salary' => 4500,
            'employer_contributions' => 800,
            'total_cost' => 5800,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get("/api/v1/payroll-runs/{$run->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // UTF-8 BOM kept for Excel compatibility.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $body = substr($csv, 3);
        $lines = array_values(array_filter(explode("\n", $body)));

        $this->assertStringContainsString('Pays', $lines[0]);
        $this->assertStringContainsString('Devise', $lines[0]);
        $this->assertStringContainsString('Période Début', $lines[0]);
        $this->assertStringContainsString('Période Fin', $lines[0]);

        $this->assertStringContainsString('EMP-042', $lines[1]);
        $this->assertStringContainsString('MA', $lines[1]);
        $this->assertStringContainsString('MAD', $lines[1]);
        $this->assertStringContainsString('2026-05-01', $lines[1]);
        $this->assertStringContainsString('2026-05-31', $lines[1]);
        $this->assertStringContainsString('5000.00', $lines[1]);
        $this->assertStringContainsString('4500.00', $lines[1]);
    }

    public function test_export_requires_manager_role(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'country_code' => 'DZ',
            'status' => 'validated',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'total_employer_cost' => 0,
            'employee_count' => 0,
        ]);

        Sanctum::actingAs($employee);

        $this->get("/api/v1/payroll-runs/{$run->id}/export")->assertStatus(403);
    }

    public function test_export_is_scoped_to_tenant(): void
    {
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        $runA = PayrollRun::query()->create([
            'company_id' => $companyA->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'country_code' => 'DZ',
            'status' => 'validated',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'total_employer_cost' => 0,
            'employee_count' => 0,
        ]);

        Sanctum::actingAs($managerB);

        $this->get("/api/v1/payroll-runs/{$runA->id}/export")->assertStatus(404);
    }
}
