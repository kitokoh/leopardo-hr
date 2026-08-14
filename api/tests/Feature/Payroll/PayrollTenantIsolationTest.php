<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
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

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        $this->companyA = $companyA;
        $this->companyB = $companyB;
        $this->managerA = $managerA;
        $this->managerB = $managerB;
    }

    /**
     * @return array{run: PayrollRun, slip: PaySlip, structure: SalaryStructure, taxSlab: TaxSlab}
     */
    private function seedPayrollData(Company $company): array
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'calculated',
            'total_gross' => 100000,
            'total_net' => 75000,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var PaySlip $slip */
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

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille A',
            'code' => 'GRID-A',
            'base_salary' => 50000,
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var TaxSlab $taxSlab */
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
            'status' => 'generated', // 'completed' removed from constraint (2026_07_25 async migration)
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
        $response = $this->getJson('/api/v1/me/pay-slips')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertTrue($ids->contains($dataA['slip']->id) === false);
    }

    public function test_archived_cabinet_payslip_is_scoped_to_company(): void
    {
        // Issue #1817 : le CabinetDocument archivé porte le company_id du run
        // (UUID tenant, plus la clé legacy 0), et l'endpoint
        // GET /me/pay-slips/{slip}/document ne résout que le document du
        // même tenant que le bulletin.
        $dataA = $this->seedPayrollData($this->companyA);

        $document = CabinetDocument::create([
            'company_id' => $this->companyA->id,
            'employee_id' => $dataA['slip']->employee_id,
            'name' => 'Bulletin de paie 06/2026',
            'original_name' => 'bulletin.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1234,
            'disk' => 'local',
            'path' => sprintf(
                'payslips/%s/2026/06/slip_%d_%d.pdf',
                $this->companyA->id,
                $dataA['slip']->employee_id,
                $dataA['run']->id
            ),
            'document_type' => CabinetDocument::TYPE_PAYSLIP,
            'read_only' => true,
        ]);

        // Le document est scopé au bon tenant (UUID), pas à la clé legacy.
        $this->assertSame($this->companyA->id, $document->company_id);

        /** @var Employee $employeeA */
        $employeeA = Employee::query()->find($dataA['slip']->employee_id);
        $this->assertInstanceOf(Employee::class, $employeeA);

        // L'employé du tenant A accède à SON document archivé.
        Sanctum::actingAs($employeeA);
        $this->getJson('/api/v1/me/pay-slips/'.$dataA['slip']->id.'/document')
            ->assertOk()
            ->assertJsonPath('data.document_id', $document->id);

        // Un manager du tenant B ne voit rien du tenant A (404).
        Sanctum::actingAs($this->managerB);
        $this->getJson('/api/v1/me/pay-slips/'.$dataA['slip']->id.'/document')->assertNotFound();
    }
}
