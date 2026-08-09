<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-19 (#1549) : revue de sécurité ciblée multi-tenant + paie.
 *
 * Tentatives croisées entre 2 tenants sur les surfaces sensibles de la paie
 * (runs, bulletins, structures salariales, barèmes IRG, exports bancaires).
 * La paie est le contrat de confiance du produit : aucune lecture/écriture
 * cross-tenant ne doit aboutir (404 par scope global BelongsToCompany, ou 403
 * par politique), et les listes ne doivent pas fuiter d'éléments de l'autre
 * tenant.
 */
class PayrollTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;
    private Company $companyB;
    private Employee $managerA;
    private Employee $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $this->managerA = Employee::factory()->manager()->create(['company_id' => $this->companyA->id]);
        $this->managerB = Employee::factory()->manager()->create(['company_id' => $this->companyB->id]);
    }

    private function seedPayrollData(Company $company): array
    {
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'calculated',
            'total_gross' => 100000,
            'total_net' => 75000,
        ]);

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'gross_salary' => 50000,
            'total_deductions' => 12500,
            'net_salary' => 37500,
            'employer_contributions' => 13000,
            'total_cost' => 63000,
            'working_days' => 26,
            'actual_days_worked' => 26,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille A',
            'code' => 'GRID-A',
            'base_salary' => 50000,
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $taxSlab = TaxSlab::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche IRG 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 0,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ]);

        return compact('run', 'slip', 'structure', 'taxSlab');
    }

    public function test_cross_tenant_payroll_run_is_inaccessible(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/payroll-runs/{$dataA['run']->id}")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$dataA['run']->id}/calculate")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$dataA['run']->id}/validate")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$dataA['run']->id}/cancel")->assertNotFound();
        $this->getJson("/api/v1/payroll-runs/{$dataA['run']->id}/summary")->assertNotFound();
        $this->getJson("/api/v1/payroll-runs/{$dataA['run']->id}/export")->assertNotFound();
        $this->getJson("/api/v1/payroll-runs/{$dataA['run']->id}/pay-slips")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$dataA['run']->id}/bank-export")->assertNotFound();
    }

    public function test_cross_tenant_pay_slip_is_inaccessible(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/pay-slips/{$dataA['slip']->id}")->assertNotFound();
        $this->getJson("/api/v1/pay-slips/{$dataA['slip']->id}/pdf")->assertNotFound();
    }

    public function test_cross_tenant_salary_structure_is_inaccessible(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/salary-structures/{$dataA['structure']->id}")->assertNotFound();
        $this->putJson("/api/v1/salary-structures/{$dataA['structure']->id}", ['name' => 'Vol']) ->assertNotFound();
        $this->deleteJson("/api/v1/salary-structures/{$dataA['structure']->id}")->assertNotFound();
    }

    public function test_cross_tenant_tax_slab_is_inaccessible(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/tax-slabs")->assertOk()->assertJsonMissing(['id' => $dataA['taxSlab']->id]);
        $this->putJson("/api/v1/tax-slabs/{$dataA['taxSlab']->id}", ['rate' => 99])->assertNotFound();
        $this->deleteJson("/api/v1/tax-slabs/{$dataA['taxSlab']->id}")->assertNotFound();
    }

    public function test_payroll_runs_list_is_scoped_to_current_tenant(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);
        $this->seedPayrollData($this->companyB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/payroll-runs')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertTrue($ids->contains($dataA['run']->id) === false, 'Le run du tenant A ne doit pas apparaître dans la liste du tenant B.');
        $this->assertNotEmpty($ids);
    }

    public function test_cross_tenant_bank_export_is_inaccessible(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        $export = BankExport::create([
            'company_id' => $this->companyA->id,
            'payroll_run_id' => $dataA['run']->id,
            'status' => 'completed',
            'file_path' => 'exports/bank-a.csv',
            'format' => 'csv_generic',
        ]);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/bank-exports/{$export->id}")->assertNotFound();
        $this->getJson("/api/v1/bank-exports/{$export->id}/download")->assertNotFound();
    }

    public function test_cross_tenant_pay_slip_of_other_company_employee_is_inaccessible_via_me_route(): void
    {
        $dataA = $this->seedPayrollData($this->companyA);

        Sanctum::actingAs($this->managerB);

        // /me/pay-slips doit être limité aux bulletins du tenant courant.
        $response = $this->getJson('/me/pay-slips')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertTrue($ids->contains($dataA['slip']->id) === false);
    }
}
