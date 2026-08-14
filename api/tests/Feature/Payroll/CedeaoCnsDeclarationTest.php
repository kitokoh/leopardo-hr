<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CedeaoCnsDeclarationGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CEDEAO (#2158) — déclaration CNSS mensuelle Burkina Faso (BF) / INPS
 * mensuelle Mali (ML), format CSV.
 *
 * Couvre : structure du CSV (colonnes obligatoires, plafonds appliqués,
 * ligne TOTAUX), calcul des cotisations par bulletin (calculé À LA MAIN,
 * aligné sur CedeaoPayrollRules::calculateSocialCharges — BF_COMPLIANCE.md
 * §3 / ML_COMPLIANCE.md §3), endpoint protégé (RBAC manager + isolation
 * tenant 404 cross-tenant + 422 pays hors BF/ML).
 */
class CedeaoCnsDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'BF', 'currency' => 'XOF']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $countryCode = 'BF'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $countryCode,
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

    public function test_bf_csv_structure_and_totals(): void
    {
        $run = $this->makeRun('BF');
        /** @var Employee $emp1 */
        $emp1 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Adama',
            'last_name' => 'Ouédraogo',
            'matricule' => 'CNSS-BF-001',
        ]);
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Fatou',
            'last_name' => 'Traoré',
            'matricule' => 'CNSS-BF-002',
        ]);
        $this->addValidatedSlip($run, $emp1, 200000.0);
        $this->addValidatedSlip($run, $emp2, 400000.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        // En-tête + 2 bulletins + ligne TOTAUX
        $this->assertCount(4, $lines);

        // En-tête avec les colonnes obligatoires
        $header = str_getcsv($lines[0]);
        $this->assertContains('matricule', $header);
        $this->assertContains('salaire_brut', $header);
        $this->assertContains('assiette_plafonnee', $header);
        $this->assertContains('retraite_salariale', $header);
        $this->assertContains('retraite_patronale', $header);
        $this->assertContains('famille_patronale', $header);
        $this->assertContains('at_patronale', $header);
        $this->assertContains('total_patronal', $header);

        // Ligne 1 — brut 200 000 (sous plafond 900 000) :
        //   assiette 200 000 · retraite salariale 5,5 % = 11 000
        //   retraite patronale 6,5 % = 13 000 · famille 7,0 % = 14 000
        //   AT 3,5 % (non plafonné) = 7 000 · total patronal 34 000
        $row1 = str_getcsv($lines[1]);
        $this->assertSame('CNSS-BF-001', $row1[0]);
        $this->assertSame('Ouédraogo', $row1[1]);
        $this->assertSame('Adama', $row1[2]);
        $this->assertSame('200000.00', $row1[3]);
        $this->assertSame('200000.00', $row1[4]);
        $this->assertSame('11000.00', $row1[5]);
        $this->assertSame('13000.00', $row1[6]);
        $this->assertSame('14000.00', $row1[7]);
        $this->assertSame('7000.00', $row1[8]);
        $this->assertSame('34000.00', $row1[9]);

        // Ligne TOTAUX : gross 600 000 · retraite emp 33 000 · retraite pat
        //   39 000 · famille 42 000 · AT 21 000 · total patronal 102 000
        $totals = str_getcsv($lines[3]);
        $this->assertSame('TOTAL', $totals[0]);
        $this->assertSame('2 bulletins', $totals[1]);
        $this->assertSame('600000.00', $totals[3]);
        $this->assertSame('33000.00', $totals[5]);
        $this->assertSame('39000.00', $totals[6]);
        $this->assertSame('42000.00', $totals[7]);
        $this->assertSame('21000.00', $totals[8]);
        $this->assertSame('102000.00', $totals[9]);
    }

    public function test_bf_cap_900k_applied(): void
    {
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Paul',
            'last_name' => 'Kaboré',
            'matricule' => 'CNSS-BF-900',
        ]);
        // Brut 1 200 000 > plafond 900 000 → retraite/famille plafonnées,
        // AT non plafonné (pilote).
        $this->addValidatedSlip($run, $emp, 1200000.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        // Calcul manuel (BF_COMPLIANCE.md §3) :
        //   assiette = min(1 200 000, 900 000) = 900 000
        //   retraite salariale = 900 000 × 5,5 % = 49 500
        //   retraite patronale = 900 000 × 6,5 % = 58 500
        //   famille = 900 000 × 7,0 % = 63 000
        //   AT (non plafonné) = 1 200 000 × 3,5 % = 42 000
        //   total patronal = 58 500 + 63 000 + 42 000 = 163 500
        $this->assertSame('1200000.00', $row[3]);
        $this->assertSame('900000.00', $row[4]);
        $this->assertSame('49500.00', $row[5]);
        $this->assertSame('58500.00', $row[6]);
        $this->assertSame('63000.00', $row[7]);
        $this->assertSame('42000.00', $row[8]);
        $this->assertSame('163500.00', $row[9]);

        $totals = (new CedeaoCnsDeclarationGenerator)->totals($run);
        $this->assertSame(900000.0, $totals['capped_base']);
        $this->assertSame(163500.0, $totals['total_patronal']);
        $this->assertSame(1, $totals['slip_count']);
    }

    public function test_ml_csv_structure_and_cap_3m(): void
    {
        $run = $this->makeRun('ML');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diallo',
            'matricule' => 'INPS-ML-001',
        ]);
        // Brut 200 000 (sous plafond 3 000 000) :
        //   retraite salariale 3,6 % = 7 200 · patronale 7,4 % = 14 800
        //   famille 4,0 % = 8 000 · AT 2,0 % = 4 000 · total patronal 26 800
        $this->addValidatedSlip($run, $emp, 200000.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        $this->assertSame('INPS-ML-001', $row[0]);
        $this->assertSame('200000.00', $row[3]);
        $this->assertSame('200000.00', $row[4]);
        $this->assertSame('7200.00', $row[5]);
        $this->assertSame('14800.00', $row[6]);
        $this->assertSame('8000.00', $row[7]);
        $this->assertSame('4000.00', $row[8]);
        $this->assertSame('26800.00', $row[9]);

        // Plafond ML 3 000 000 : brut 4 000 000 → retraite plafonnée à
        // 3 000 000, famille/AT non plafonnées (pilote).
        $run2 = $this->makeRun('ML');
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Awa',
            'last_name' => 'Cissé',
            'matricule' => 'INPS-ML-002',
        ]);
        $this->addValidatedSlip($run2, $emp2, 4000000.0);

        $csv2 = (new CedeaoCnsDeclarationGenerator)->generate($run2);
        $lines2 = array_values(array_filter(explode("\n", $csv2), fn ($l) => trim($l) !== ''));
        $row2 = str_getcsv($lines2[1]);
        // Calcul manuel (ML_COMPLIANCE.md §3) :
        //   assiette = min(4 000 000, 3 000 000) = 3 000 000
        //   retraite salariale = 3 000 000 × 3,6 % = 108 000
        //   retraite patronale = 3 000 000 × 7,4 % = 222 000
        //   famille (non plafonné) = 4 000 000 × 4,0 % = 160 000
        //   AT (non plafonné) = 4 000 000 × 2,0 % = 80 000
        //   total patronal = 222 000 + 160 000 + 80 000 = 462 000
        $this->assertSame('4000000.00', $row2[3]);
        $this->assertSame('3000000.00', $row2[4]);
        $this->assertSame('108000.00', $row2[5]);
        $this->assertSame('222000.00', $row2[6]);
        $this->assertSame('160000.00', $row2[7]);
        $this->assertSame('80000.00', $row2[8]);
        $this->assertSame('462000.00', $row2[9]);
    }

    public function test_csv_injection_protected(): void
    {
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '=2+5',
            'last_name' => '+SUM(A1:A2)',
            'matricule' => '@cmd',
        ]);
        $this->addValidatedSlip($run, $emp, 200000.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        // #1922 : les cellules commençant par =, +, -, @ sont neutralisées.
        $this->assertSame("'=2+5", $row[2]);
        $this->assertSame("'+SUM(A1:A2)", $row[1]);
        $this->assertSame("'@cmd", $row[0]);
    }

    public function test_generator_rejects_non_bf_ml_country(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $run = $this->makeRun('CI');
        (new CedeaoCnsDeclarationGenerator)->generate($run);
    }

    public function test_endpoint_requires_bf_ml_run(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        // Run CI → 422 (déclaration CEDEAO CNSS/INPS réservée BF/ML).
        $run = $this->makeRun('CI');
        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cedeao-cns");
        $response->assertStatus(422);
    }

    public function test_endpoint_cross_tenant_404(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'BF', 'currency' => 'XOF']);
        $run = $this->makeRun('BF');
        $run->update(['company_id' => $otherCompany->id]);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cedeao-cns");
        $response->assertStatus(404);
    }

    public function test_endpoint_downloads_csv(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Adama',
            'last_name' => 'Ouédraogo',
            'matricule' => 'CNSS-BF-001',
        ]);
        $this->addValidatedSlip($run, $emp, 200000.0);

        $response = $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cedeao-cns");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertDownload();
        $content = $response->streamedContent();
        $this->assertStringContainsString('matricule', $content);
        $this->assertStringContainsString('TOTAL', $content);
    }
}
