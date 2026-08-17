<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CnpsDeclarationGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CEMAC/CM (#1823) — déclaration CNPS mensuelle Cameroun (format DAS).
 *
 * Couvre : structure du CSV (colonnes obligatoires, plafond 750k appliqué,
 * ligne TOTAUX), calcul des cotisations par bulletin, endpoint protégé
 * (RBAC manager + isolation tenant 404 cross-tenant).
 */
class CnpsDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function makeRun(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CM',
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        return $run;
    }

    private function addValidatedSlip(PayrollRun $run, Employee $employee, float $gross): PaySlip
    {
        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'total_deductions' => 0,
            'net_salary' => $gross,
            'employer_contributions' => 0,
            'total_cost' => $gross,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'status' => 'validated',
        ]);

        return $slip;
    }

    public function test_csv_structure_and_totals(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp1 */
        $emp1 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Mbarga',
            'cnps_matricule' => 'CNPS-001',
        ]);
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Aline',
            'last_name' => 'Ngo',
            'cnps_matricule' => 'CNPS-002',
        ]);
        $this->addValidatedSlip($run, $emp1, 200000.0);
        $this->addValidatedSlip($run, $emp2, 400000.0);

        $csv = (new CnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        // En-tête + 2 bulletins + ligne TOTAUX
        $this->assertCount(4, $lines);

        // En-tête avec les colonnes obligatoires
        $header = str_getcsv($lines[0]);
        $this->assertContains('matricule_cnps', $header);
        $this->assertContains('salaire_brut', $header);
        $this->assertContains('assiette_plafonnee', $header);
        $this->assertContains('vieillesse_salariale', $header);
        $this->assertContains('vieillesse_patronale', $header);
        $this->assertContains('famille_patronale', $header);
        $this->assertContains('at_patronale', $header);
        $this->assertContains('total_patronal', $header);

        // Ligne 1 — brut 200 000 (sous plafond) :
        //   assiette 200 000 · vieillesse salariale 8 400 · vieillesse pat. 8 400
        //   famille 14 000 · AT 4 000 · total patronal 26 400
        $row1 = str_getcsv($lines[1]);
        $this->assertSame('CNPS-001', $row1[0]);
        $this->assertSame('Mbarga', $row1[1]);
        $this->assertSame('Jean', $row1[2]);
        $this->assertSame('200000.00', $row1[3]);
        $this->assertSame('200000.00', $row1[4]);
        $this->assertSame('8400.00', $row1[5]);
        $this->assertSame('8400.00', $row1[6]);
        $this->assertSame('14000.00', $row1[7]);
        $this->assertSame('4000.00', $row1[8]);
        $this->assertSame('26400.00', $row1[9]);

        // Ligne TOTAUX : gross 600 000 · assiette 600 000 · vieillesse emp 25 200
        //   vieillesse pat 25 200 · famille 42 000 · AT 12 000 · total patronal 79 200
        $totals = str_getcsv($lines[3]);
        $this->assertSame('TOTAL', $totals[0]);
        $this->assertSame('2 bulletins', $totals[1]);
        $this->assertSame('600000.00', $totals[3]);
        $this->assertSame('600000.00', $totals[4]);
        $this->assertSame('25200.00', $totals[5]);
        $this->assertSame('25200.00', $totals[6]);
        $this->assertSame('42000.00', $totals[7]);
        $this->assertSame('12000.00', $totals[8]);
        $this->assertSame('79200.00', $totals[9]);
    }

    public function test_cap_750k_applied_in_declaration(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Paul',
            'last_name' => 'Biya',
            'cnps_matricule' => 'CNPS-750',
        ]);
        // Brut 1 000 000 > plafond 750 000 → assiette plafonnée.
        $this->addValidatedSlip($run, $emp, 1000000.0);

        $csv = (new CnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        // Calcul manuel (CM_COMPLIANCE.md §2) :
        //   assiette = min(1 000 000, 750 000) = 750 000
        //   vieillesse salariale = 750 000 × 4,2 % = 31 500
        //   vieillesse patronale = 31 500 · famille = 52 500 · AT (non plafonné) = 20 000
        //   total patronal = 31 500 + 52 500 + 20 000 = 104 000
        $this->assertSame('1000000.00', $row[3]);
        $this->assertSame('750000.00', $row[4]);
        $this->assertSame('31500.00', $row[5]);
        $this->assertSame('31500.00', $row[6]);
        $this->assertSame('52500.00', $row[7]);
        $this->assertSame('20000.00', $row[8]);
        $this->assertSame('104000.00', $row[9]);

        // Totaux cohérents avec le plafonnement.
        $totals = (new CnpsDeclarationGenerator)->totals($run);
        $this->assertSame(750000.0, $totals['capped_base']);
        $this->assertSame(104000.0, $totals['total_patronal']);
        $this->assertSame(1, $totals['slip_count']);
    }

    public function test_endpoint_downloads_csv(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Test',
            'last_name' => 'Unitaire',
            'cnps_matricule' => 'CNPS-T1',
        ]);
        $this->addValidatedSlip($run, $emp, 200000.0);

        Sanctum::actingAs($this->manager);

        $response = $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('matricule_cnps', $response->streamedContent());
        $this->assertStringContainsString('CNPS-T1', $response->streamedContent());
        $this->assertStringContainsString('TOTAL', $response->streamedContent());
    }

    public function test_cross_tenant_declaration_blocked(): void
    {
        $run = $this->makeRun();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);
        Sanctum::actingAs($otherManager);

        $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertNotFound();
    }

    public function test_employee_cannot_download_declaration(): void
    {
        $run = $this->makeRun();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);

        $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertForbidden();
    }

    public function test_manager_rh_cannot_download_declaration(): void
    {
        $run = $this->makeRun();
        // RBAC resserré : manager sans rôle principal/comptable → 403.
        /** @var Employee $managerRh */
        $managerRh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($managerRh);

        $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertForbidden();
    }

    public function test_non_cm_run_rejected_with_422(): void
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'SN',
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")
            ->assertStatus(422)
            ->assertJson(['message' => 'Ce run de paie ne concerne pas le Cameroun (CNPS CM).']);
    }

    public function test_matricule_falls_back_to_internal_matricule(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Sans',
            'last_name' => 'MatriculeCnps',
            'matricule' => 'EMP-4242',
            'cnps_matricule' => null,
        ]);
        $this->addValidatedSlip($run, $emp, 100000.0);

        $csv = (new CnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        $this->assertSame('EMP-4242', $row[0]);
    }

    public function test_matricule_falls_back_to_employee_id(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Sans',
            'last_name' => 'MatriculeDuTout',
            'matricule' => null,
            'cnps_matricule' => null,
        ]);
        $this->addValidatedSlip($run, $emp, 100000.0);

        $csv = (new CnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        $this->assertSame((string) $emp->id, $row[0]);
    }
}
