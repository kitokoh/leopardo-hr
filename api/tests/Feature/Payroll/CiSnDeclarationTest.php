<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CnssDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\IpresDeclarationGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CEDEAO (#1830) — déclarations sociales CNSS Côte d'Ivoire + IPRES/CSS
 * Sénégal (CSV mensuel).
 *
 * Couvre : structure du CSV (colonnes obligatoires, plafonds appliqués,
 * ligne TOTAUX), calculs par bulletin, endpoints protégés (RBAC manager +
 * isolation tenant 404 + 422 si mauvais pays).
 */
class CiSnDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $country): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        return $run;
    }

    private function addValidatedSlip(PayrollRun $run, Employee $employee, float $gross): void
    {
        PaySlip::create([
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
    }

    // ── CNSS Côte d'Ivoire ──────────────────────────────────────────────

    public function test_ci_csv_structure_and_totals(): void
    {
        $run = $this->makeRun('CI');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Aya',
            'last_name' => 'Kouassi',
            'cnss_ci_matricule' => 'CNSS-CI-001',
        ]);
        $this->addValidatedSlip($run, $emp, 400000.0);

        $csv = (new CnssDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $this->assertCount(3, $lines); // en-tête + 1 bulletin + TOTAUX

        $header = str_getcsv($lines[0]);
        $this->assertContains('matricule_cnss', $header);
        $this->assertContains('assiette_plafonnee', $header);
        $this->assertContains('retraite_salariale', $header);
        $this->assertContains('retraite_patronale', $header);
        $this->assertContains('famille_patronale', $header);
        $this->assertContains('at_patronale', $header);
        $this->assertContains('total_salarial', $header);
        $this->assertContains('total_patronal', $header);

        // Calcul manuel (issues #1830 + #1913) — brut 400 000 XOF :
        //   retraite salariale 3,2 % = 12 800 · retraite patronale 4,5 % = 18 000
        //   famille 5,75 % plafonnée à 70 000 = 4 025 · AT 2 % plafonné à 70 000 = 1 400
        //   total salarial 12 800 · total patronal 23 425
        $row = str_getcsv($lines[1]);
        $this->assertSame('CNSS-CI-001', $row[0]);
        $this->assertSame('Kouassi', $row[1]);
        $this->assertSame('Aya', $row[2]);
        $this->assertSame('400000.00', $row[3]);
        $this->assertSame('400000.00', $row[4]);
        $this->assertSame('12800.00', $row[5]);
        $this->assertSame('18000.00', $row[6]);
        $this->assertSame('4025.00', $row[7]);
        $this->assertSame('1400.00', $row[8]);
        $this->assertSame('12800.00', $row[9]);
        $this->assertSame('23425.00', $row[10]);

        // Totaux cohérents.
        $totals = (new CnssDeclarationGenerator)->totals($run);
        $this->assertSame(12800.0, $totals['retraite_emp']);
        $this->assertSame(23425.0, $totals['total_pat']);
        $this->assertSame(1, $totals['slip_count']);
    }

    public function test_ci_cap_1647315_applied(): void
    {
        $run = $this->makeRun('CI');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Ibrahima',
            'last_name' => 'Sanogo',
            'cnss_ci_matricule' => 'CNSS-CI-750',
        ]);
        // Brut 2 000 000 > plafond 1 647 315.
        $this->addValidatedSlip($run, $emp, 2000000.0);

        $csv = (new CnssDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        // Calcul manuel (issues #1830/#1898 + #1913) — assiette plafonnée pour
        // retraite = min(2 000 000, 1 647 315), famille et AT plafonnées à 70 000
        // (guide CNPS, #1913 — aligné moteur calculateSocialCharges) :
        //   retraite salariale = 1 647 315 × 3,2 % = 52 714,08
        //   retraite patronale = 1 647 315 × 4,5 % = 74 129,18
        //   famille = 70 000 × 5,75 % = 4 025,00 · AT = 70 000 × 2 % = 1 400,00
        //   total patronal = 79 554,18
        $this->assertSame('2000000.00', $row[3]);
        $this->assertSame('1647315.00', $row[4]);
        $this->assertSame('52714.08', $row[5]);
        $this->assertSame('74129.18', $row[6]);
        $this->assertSame('4025.00', $row[7]);
        $this->assertSame('1400.00', $row[8]);
        $this->assertSame('79554.18', $row[10]);
    }

    public function test_ci_totals_row_matches_line_sums_above_cap(): void
    {
        // Régression #1922 + #1913 : le total AT était calculé sur l'assiette
        // PLAFONNÉE dans totals() alors que les lignes utilisaient le brut réel
        // → la ligne TOTAUX ne valait pas la somme des lignes. Depuis #1913,
        // famille et AT sont plafonnées à 70 000 (CNPS) sur les lignes ET totals()
        // → la somme des lignes vaut le total (propriété conservée).
        $run = $this->makeRun('CI');

        /** @var Employee $above */
        $above = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Aya',
            'last_name' => 'Kouassi',
            'cnss_ci_matricule' => 'CNSS-CI-001',
        ]);
        $this->addValidatedSlip($run, $above, 2000000.0);

        /** @var Employee $below */
        $below = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Ibrahima',
            'last_name' => 'Sanogo',
            'cnss_ci_matricule' => 'CNSS-CI-002',
        ]);
        $this->addValidatedSlip($run, $below, 400000.0);

        $csv = (new CnssDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        // Lignes : AT = 70 000 × 2 % = 1 400,00 (cap #1913) pour chaque ligne.
        $this->assertSame('1400.00', str_getcsv($lines[1])[8]);
        $this->assertSame('1400.00', str_getcsv($lines[2])[8]);

        // TOTAUX : AT = 2 800,00 (somme des lignes, aligné sur le cap #1913).
        $totalsRow = str_getcsv($lines[3]);
        $this->assertSame('TOTAL', $totalsRow[0]);
        $this->assertSame('2800.00', $totalsRow[8]);
        $this->assertSame('102979.18', $totalsRow[10]); // 79 554,18 + 23 425,00

        // totals() doit être aligné sur les lignes.
        $totals = (new CnssDeclarationGenerator)->totals($run);
        $this->assertSame(2800.0, $totals['at_pat']);
        $this->assertSame(102979.18, $totals['total_pat']);
        $this->assertSame(2, $totals['slip_count']);
    }

    public function test_csv_prevents_formula_injection_ci_and_sn(): void
    {
        // Régression #1922 : un nom/matricule commençant par =, +, -, @, tab
        // ou saut de ligne ne doit JAMAIS être interprété comme formule par
        // Excel/LibreOffice — préfixe ' neutralisant appliqué.
        $runCi = $this->makeRun('CI');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '=1+1',
            'last_name' => '@SUM(A1:A9)',
            'cnss_ci_matricule' => '+CMD',
        ]);
        $this->addValidatedSlip($runCi, $emp, 400000.0);

        $csvCi = (new CnssDeclarationGenerator)->generate($runCi);
        $linesCi = array_values(array_filter(explode("\n", $csvCi), fn ($l) => trim($l) !== ''));
        $rowCi = str_getcsv($linesCi[1]);
        $this->assertSame("'=1+1", $rowCi[2]);
        $this->assertSame("'@SUM(A1:A9)", $rowCi[1]);
        $this->assertSame("'+CMD", $rowCi[0]);

        $runSn = $this->makeRun('SN');
        $emp->update([
            'ipres_matricule' => '-2+3',
            'ipres_category' => 'cadre',
            'last_name' => 'Dia',
            'first_name' => 'Moussa',
        ]);
        $this->addValidatedSlip($runSn, $emp, 500000.0);

        $csvSn = (new IpresDeclarationGenerator)->generate($runSn);
        $linesSn = array_values(array_filter(explode("\n", $csvSn), fn ($l) => trim($l) !== ''));
        $rowSn = str_getcsv($linesSn[1]);
        $this->assertSame("'-2+3", $rowSn[0]);
    }

    // ── IPRES/CSS Sénégal ───────────────────────────────────────────────

    public function test_sn_csv_general_employee(): void
    {
        $run = $this->makeRun('SN');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Fatou',
            'last_name' => 'Diop',
            'ipres_matricule' => 'IPRES-SN-001',
            'ipres_category' => 'general',
        ]);
        $this->addValidatedSlip($run, $emp, 300000.0);

        $csv = (new IpresDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        // Calcul manuel (issue #1830 + #1913) — employé général, brut 300 000 XOF :
        //   T1 = min(300 000, 432 000) = 300 000 · T2 = 0 (non cadre)
        //   T1 salariale 5,6 % = 16 800 · T1 patronale 8,4 % = 25 200
        //   T2 salariale 0 · T2 patronale 0 · CSS famille = min(300 000, 63 000)
        //     × 3 % = 1 890 (plafond CSS #1913)
        //   total patronal = 25 200 + 0 + 1 890 = 27 090
        $this->assertSame('IPRES-SN-001', $row[0]);
        $this->assertSame('Diop', $row[1]);
        $this->assertSame('Fatou', $row[2]);
        $this->assertSame('general', $row[3]);
        $this->assertSame('300000.00', $row[4]);
        $this->assertSame('300000.00', $row[5]);
        $this->assertSame('16800.00', $row[6]);
        $this->assertSame('25200.00', $row[7]);
        $this->assertSame('0.00', $row[8]);
        $this->assertSame('0.00', $row[9]);
        $this->assertSame('0.00', $row[10]);
        $this->assertSame('1890.00', $row[11]);
        $this->assertSame('27090.00', $row[12]);
    }

    public function test_sn_csv_cadre_with_t2(): void
    {
        $run = $this->makeRun('SN');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Moussa',
            'last_name' => 'Ndiaye',
            'ipres_matricule' => 'IPRES-SN-002',
            'ipres_category' => 'cadre',
        ]);
        // Brut 1 000 000 > T1 (432 000) → T2 = 1 000 000 − 432 000 = 568 000.
        $this->addValidatedSlip($run, $emp, 1000000.0);

        $csv = (new IpresDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        // Calcul manuel (issue #1830) — cadre, brut 1 000 000 :
        //   T1 = 432 000 → salariale 5,6 % = 24 192 · patronale 8,4 % = 36 288
        //   T2 = 1 000 000 − 432 000 = 568 000 → salariale 2,4 % = 13 632
        //        patronale 3,6 % = 20 448
        //   CSS famille = min(1 000 000, 63 000) × 3 % = 1 890 (plafond CSS #1913)
        //   total patronal = 36 288 + 20 448 + 1 890 = 58 626
        $this->assertSame('cadre', $row[3]);
        $this->assertSame('432000.00', $row[5]);
        $this->assertSame('24192.00', $row[6]);
        $this->assertSame('36288.00', $row[7]);
        $this->assertSame('568000.00', $row[8]);
        $this->assertSame('13632.00', $row[9]);
        $this->assertSame('20448.00', $row[10]);
        $this->assertSame('1890.00', $row[11]);
        $this->assertSame('58626.00', $row[12]);
    }

    // ── Endpoints + RBAC ────────────────────────────────────────────────

    public function test_endpoints_download_csv(): void
    {
        $ciRun = $this->makeRun('CI');
        /** @var Employee $ciEmp */
        $ciEmp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'cnss_ci_matricule' => 'CNSS-CI-9',
        ]);
        $this->addValidatedSlip($ciRun, $ciEmp, 400000.0);

        $snRun = $this->makeRun('SN');
        /** @var Employee $snEmp */
        $snEmp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'ipres_matricule' => 'IPRES-SN-9',
        ]);
        $this->addValidatedSlip($snRun, $snEmp, 300000.0);

        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/payroll-runs/{$ciRun->id}/declarations/cnss-ci")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->get("/api/v1/payroll-runs/{$snRun->id}/declarations/ipres-sn")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_wrong_country_returns_422(): void
    {
        $dzRun = $this->makeRun('DZ');

        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/payroll-runs/{$dzRun->id}/declarations/cnss-ci")->assertStatus(422);
        $this->get("/api/v1/payroll-runs/{$dzRun->id}/declarations/ipres-sn")->assertStatus(422);
    }

    public function test_cross_tenant_blocked(): void
    {
        $ciRun = $this->makeRun('CI');

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);
        Sanctum::actingAs($otherManager);

        $this->get("/api/v1/payroll-runs/{$ciRun->id}/declarations/cnss-ci")->assertNotFound();
        $this->get("/api/v1/payroll-runs/{$ciRun->id}/declarations/ipres-sn")->assertNotFound();
    }
}
