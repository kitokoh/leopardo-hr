<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CemacCnsDeclarationGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CEMAC (#2155) — déclaration CNSS mensuelle Gabon (GA) / Congo (CG),
 * format CSV.
 *
 * Couvre : structure du CSV (colonnes obligatoires, plafonds appliqués,
 * ligne TOTAUX), calcul des cotisations par bulletin (calculé À LA MAIN,
 * aligné sur CemacPayrollRules::calculateSocialCharges — GA_COMPLIANCE.md
 * §3 / CG_COMPLIANCE.md §3), endpoint protégé (RBAC manager + isolation
 * tenant 404 cross-tenant + 422 pays hors GA/CG).
 */
class CemacCnsDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'GA', 'currency' => 'XAF']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $countryCode = 'GA'): PayrollRun
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

    public function test_ga_csv_structure_and_totals(): void
    {
        $run = $this->makeRun('GA');
        /** @var Employee $emp1 */
        $emp1 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Mba',
            'matricule' => 'CNSS-GA-001',
        ]);
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Marie',
            'last_name' => 'Ondo',
            'matricule' => 'CNSS-GA-002',
        ]);
        $this->addValidatedSlip($run, $emp1, 200000.0);
        $this->addValidatedSlip($run, $emp2, 400000.0);

        $csv = (new CemacCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        // En-tête + 2 bulletins + ligne TOTAUX
        $this->assertCount(4, $lines);

        $header = str_getcsv($lines[0]);
        $this->assertContains('matricule', $header);
        $this->assertContains('salaire_brut', $header);
        $this->assertContains('assiette_plafonnee', $header);
        $this->assertContains('retraite_salariale', $header);
        $this->assertContains('retraite_patronale', $header);
        $this->assertContains('famille_patronale', $header);
        $this->assertContains('at_patronale', $header);
        $this->assertContains('total_patronal', $header);

        // Ligne 1 — brut 200 000 (sous plafond 3 000 000) :
        //   assiette 200 000 · retraite salariale 2,5 % = 5 000
        //   retraite patronale 5,0 % = 10 000 · famille 8,0 % = 16 000
        //   AT 3,0 % (non plafonné) = 6 000 · total patronal 32 000
        $row1 = str_getcsv($lines[1]);
        $this->assertSame('CNSS-GA-001', $row1[0]);
        $this->assertSame('Mba', $row1[1]);
        $this->assertSame('Jean', $row1[2]);
        $this->assertSame('200000.00', $row1[3]);
        $this->assertSame('200000.00', $row1[4]);
        $this->assertSame('5000.00', $row1[5]);
        $this->assertSame('10000.00', $row1[6]);
        $this->assertSame('16000.00', $row1[7]);
        $this->assertSame('6000.00', $row1[8]);
        $this->assertSame('32000.00', $row1[9]);

        // Ligne TOTAUX : gross 600 000 · retraite emp 15 000 · retraite pat
        //   30 000 · famille 48 000 · AT 18 000 · total patronal 96 000
        $totals = str_getcsv($lines[3]);
        $this->assertSame('TOTAL', $totals[0]);
        $this->assertSame('2 bulletins', $totals[1]);
        $this->assertSame('600000.00', $totals[3]);
        $this->assertSame('15000.00', $totals[5]);
        $this->assertSame('30000.00', $totals[6]);
        $this->assertSame('48000.00', $totals[7]);
        $this->assertSame('18000.00', $totals[8]);
        $this->assertSame('96000.00', $totals[9]);
    }

    public function test_ga_cap_3m_applied(): void
    {
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Paul',
            'last_name' => 'Nguema',
            'matricule' => 'CNSS-GA-3M',
        ]);
        // Brut 4 000 000 > plafond 3 000 000 → retraite/famille plafonnées,
        // AT non plafonné (pilote).
        $this->addValidatedSlip($run, $emp, 4000000.0);

        $csv = (new CemacCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        // Calcul manuel (GA_COMPLIANCE.md §3) :
        //   assiette = min(4 000 000, 3 000 000) = 3 000 000
        //   retraite salariale = 3 000 000 × 2,5 % = 75 000
        //   retraite patronale = 3 000 000 × 5,0 % = 150 000
        //   famille = 3 000 000 × 8,0 % = 240 000
        //   AT (non plafonné) = 4 000 000 × 3,0 % = 120 000
        //   total patronal = 150 000 + 240 000 + 120 000 = 510 000
        $this->assertSame('4000000.00', $row[3]);
        $this->assertSame('3000000.00', $row[4]);
        $this->assertSame('75000.00', $row[5]);
        $this->assertSame('150000.00', $row[6]);
        $this->assertSame('240000.00', $row[7]);
        $this->assertSame('120000.00', $row[8]);
        $this->assertSame('510000.00', $row[9]);

        $totals = (new CemacCnsDeclarationGenerator)->totals($run);
        $this->assertSame(3000000.0, $totals['capped_base']);
        $this->assertSame(510000.0, $totals['total_patronal']);
        $this->assertSame(1, $totals['slip_count']);
    }

    public function test_cg_csv_structure_and_cap_2_5m(): void
    {
        $run = $this->makeRun('CG');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Côme',
            'last_name' => 'Makosso',
            'matricule' => 'CNSS-CG-001',
        ]);
        // Brut 200 000 (sous plafond 2 500 000) :
        //   retraite salariale 4,0 % = 8 000 · patronale 8,0 % = 16 000
        //   famille 10,0 % = 20 000 · AT 3,0 % = 6 000 · total patronal 42 000
        $this->addValidatedSlip($run, $emp, 200000.0);

        $csv = (new CemacCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        $this->assertSame('CNSS-CG-001', $row[0]);
        $this->assertSame('200000.00', $row[3]);
        $this->assertSame('200000.00', $row[4]);
        $this->assertSame('8000.00', $row[5]);
        $this->assertSame('16000.00', $row[6]);
        $this->assertSame('20000.00', $row[7]);
        $this->assertSame('6000.00', $row[8]);
        $this->assertSame('42000.00', $row[9]);

        // Plafond CG 2 500 000 : brut 3 000 000 → retraite/famille
        // plafonnées, AT non plafonné (pilote).
        $run2 = $this->makeRun('CG');
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Awa',
            'last_name' => 'Samba',
            'matricule' => 'CNSS-CG-002',
        ]);
        $this->addValidatedSlip($run2, $emp2, 3000000.0);

        $csv2 = (new CemacCnsDeclarationGenerator)->generate($run2);
        $lines2 = array_values(array_filter(explode("\n", $csv2), fn ($l) => trim($l) !== ''));
        $row2 = str_getcsv($lines2[1]);
        // Calcul manuel (CG_COMPLIANCE.md §3) :
        //   assiette = min(3 000 000, 2 500 000) = 2 500 000
        //   retraite salariale = 2 500 000 × 4,0 % = 100 000
        //   retraite patronale = 2 500 000 × 8,0 % = 200 000
        //   famille = 2 500 000 × 10,0 % = 250 000
        //   AT (non plafonné) = 3 000 000 × 3,0 % = 90 000
        //   total patronal = 200 000 + 250 000 + 90 000 = 540 000
        $this->assertSame('3000000.00', $row2[3]);
        $this->assertSame('2500000.00', $row2[4]);
        $this->assertSame('100000.00', $row2[5]);
        $this->assertSame('200000.00', $row2[6]);
        $this->assertSame('250000.00', $row2[7]);
        $this->assertSame('90000.00', $row2[8]);
        $this->assertSame('540000.00', $row2[9]);
    }

    public function test_csv_injection_protected(): void
    {
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '=2+5',
            'last_name' => '+SUM(A1:A2)',
            'matricule' => '@cmd',
        ]);
        $this->addValidatedSlip($run, $emp, 200000.0);

        $csv = (new CemacCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $row = str_getcsv($lines[1]);
        // #1922 : les cellules commençant par =, +, -, @ sont neutralisées.
        $this->assertSame("'=2+5", $row[2]);
        $this->assertSame("'+SUM(A1:A2)", $row[1]);
        $this->assertSame("'@cmd", $row[0]);
    }

    public function test_generator_rejects_non_ga_cg_country(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $run = $this->makeRun('CM');
        (new CemacCnsDeclarationGenerator)->generate($run);
    }

    public function test_endpoint_requires_ga_cg_run(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        // Run CM → 422 (déclaration CEMAC CNSS réservée GA/CG).
        $run = $this->makeRun('CM');
        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cemac-cns");
        $response->assertStatus(422);
    }

    public function test_endpoint_cross_tenant_404(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'GA', 'currency' => 'XAF']);
        $run = $this->makeRun('GA');
        $run->update(['company_id' => $otherCompany->id]);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cemac-cns");
        $response->assertStatus(404);
    }

    public function test_endpoint_downloads_csv(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Mba',
            'matricule' => 'CNSS-GA-001',
        ]);
        $this->addValidatedSlip($run, $emp, 200000.0);

        $response = $this->get("/api/v1/payroll-runs/{$run->id}/declarations/cemac-cns");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertDownload();
        $content = $response->streamedContent();
        $this->assertStringContainsString('matricule', $content);
        $this->assertStringContainsString('TOTAL', $content);
    }
}
