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
 * CEDEAO (#2158) — déclarations sociales CNSS Burkina Faso + INPS Mali
 * (CSV mensuel).
 *
 * Couvre : structure du CSV, calculs par bulletin calculés À LA MAIN
 * (constitution §III — SMIG, cadre moyen, haut salaire), ligne TOTAUX,
 * protection injection formule CSV (#1922), endpoints protégés (RBAC
 * manager principal/comptable + isolation tenant 404 + 422 mauvais pays).
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

    // ── CNSS Burkina Faso ──────────────────────────────────────────────

    public function test_bf_csv_structure_and_smig_hand_calculated(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §4 + CedeaoPayrollRules #1829),
        // brut = SMIG BF 34 664 XOF :
        //   retraite salariale = 34 664 × 5,5 % = 1 906,52
        //   retraite patronale = 34 664 × 6,5 % = 2 253,16
        //   famille patronale  = 34 664 × 7,0 % = 2 426,48
        //   AT patronal        = 34 664 × 3,5 % = 1 213,24 (non plafonné)
        //   → salarié 1 906,52 · patronal 5 892,88
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Awa',
            'last_name' => 'Ouédraogo',
            'cnss_bf_matricule' => 'CNSS-BF-001',
        ]);
        $this->addValidatedSlip($run, $emp, 34664.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $this->assertCount(3, $lines); // en-tête + 1 bulletin + TOTAUX
        $this->assertStringContainsString('"CNSS-BF-001","Ouédraogo","Awa"', $lines[1]);
        $this->assertStringContainsString('"34664.00","34664.00","1906.52","2253.16","2426.48","1213.24","1906.52","5892.88"', $lines[1]);
        $this->assertStringContainsString('"TOTAL","1 bulletins"', $lines[2]);
        $this->assertStringContainsString('"5892.88"', $lines[2]);
    }

    public function test_bf_mid_salary_hand_calculated(): void
    {
        // Brut 500 000 (< plafond 900 000) :
        //   salarié 27 500 · patronal 32 500 + 35 000 + 17 500 = 85 000
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Ibrahim', 'last_name' => 'Traoré', 'cnss_bf_matricule' => 'CNSS-BF-002']);
        $this->addValidatedSlip($run, $emp, 500000.0);

        $lines = array_values(array_filter(explode("\n", (new CedeaoCnsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"27500.00","32500.00","35000.00","17500.00","27500.00","85000.00"', $lines[1]);
    }

    public function test_bf_high_salary_cap_applied_hand_calculated(): void
    {
        // Brut 2 000 000 > plafond retraite/famille 900 000 :
        //   retraite base 900 000 → salarié 49 500 · patronal 58 500 + 63 000
        //   + AT 2 000 000 × 3,5 % = 70 000 → 191 500
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Fatou', 'last_name' => 'Zongo', 'cnss_bf_matricule' => 'CNSS-BF-003']);
        $this->addValidatedSlip($run, $emp, 2000000.0);

        $lines = array_values(array_filter(explode("\n", (new CedeaoCnsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"2000000.00","900000.00","49500.00","58500.00","63000.00","70000.00","49500.00","191500.00"', $lines[1]);
    }

    // ── INPS Mali ──────────────────────────────────────────────────────

    public function test_ml_csv_structure_and_smig_hand_calculated(): void
    {
        // Calcul manuel (ML_COMPLIANCE.md §4 + CedeaoPayrollRules #1829),
        // brut = SMIG ML 40 000 XOF :
        //   retraite salariale = 40 000 × 3,6 % = 1 440,00
        //   retraite patronale = 40 000 × 7,4 % = 2 960,00
        //   famille patronale  = 40 000 × 4,0 % = 1 600,00 (non plafonné)
        //   AT patronal        = 40 000 × 2,0 % = 800,00 (non plafonné)
        //   → salarié 1 440,00 · patronal 5 360,00
        $run = $this->makeRun('ML');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diallo',
            'inps_ml_matricule' => 'INPS-ML-001',
        ]);
        $this->addValidatedSlip($run, $emp, 40000.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('"INPS-ML-001","Diallo","Moussa"', $lines[1]);
        $this->assertStringContainsString('"40000.00","40000.00","1440.00","2960.00","1600.00","800.00","1440.00","5360.00"', $lines[1]);
        $this->assertStringContainsString('"TOTAL","1 bulletins"', $lines[2]);
    }

    public function test_ml_high_salary_cap_applied_hand_calculated(): void
    {
        // Brut 4 000 000 > plafond retraite 3 000 000 :
        //   retraite base 3 000 000 → salarié 108 000 · patronal 222 000
        //   + famille 4 000 000 × 4,0 % = 160 000 + AT 4 000 000 × 2,0 %
        //   = 80 000 → 462 000
        $run = $this->makeRun('ML');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Aminata', 'last_name' => 'Cissé', 'inps_ml_matricule' => 'INPS-ML-002']);
        $this->addValidatedSlip($run, $emp, 4000000.0);

        $lines = array_values(array_filter(explode("\n", (new CedeaoCnsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"4000000.00","3000000.00","108000.00","222000.00","160000.00","80000.00","108000.00","462000.00"', $lines[1]);
    }

    // ── Sécurité & contrats ────────────────────────────────────────────

    public function test_csv_injection_neutralized(): void
    {
        // #1922 : un nom commençant par « = » ne doit pas devenir une
        // formule Excel.
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '=HYPERLINK("http://evil.example")',
            'last_name' => 'Hacker',
            'cnss_bf_matricule' => '=1+1',
        ]);
        $this->addValidatedSlip($run, $emp, 34664.0);

        $csv = (new CedeaoCnsDeclarationGenerator)->generate($run);

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
    }

    public function test_endpoint_bf_returns_csv_for_manager(): void
    {
        $run = $this->makeRun('BF');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Awa', 'last_name' => 'Ouédraogo', 'cnss_bf_matricule' => 'CNSS-BF-001']);
        $this->addValidatedSlip($run, $emp, 34664.0);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-bf");
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('CNSS_BF_', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('"CNSS-BF-001"', $response->streamedContent());
    }

    public function test_endpoint_ml_returns_csv_for_manager(): void
    {
        $run = $this->makeRun('ML');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Moussa', 'last_name' => 'Diallo', 'inps_ml_matricule' => 'INPS-ML-001']);
        $this->addValidatedSlip($run, $emp, 40000.0);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/inps-ml");
        $response->assertOk();
        $this->assertStringContainsString('INPS_ML_', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('"INPS-ML-001"', $response->streamedContent());
    }

    public function test_endpoint_rejects_wrong_country_run(): void
    {
        $run = $this->makeRun('CI'); // run CI, pas BF

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-bf")->assertStatus(422);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/inps-ml")->assertStatus(422);
    }

    public function test_endpoint_forbidden_for_employee(): void
    {
        $run = $this->makeRun('BF');
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-bf")->assertStatus(403);
    }

    public function test_endpoint_tenant_isolation(): void
    {
        $run = $this->makeRun('BF');
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'BF', 'currency' => 'XOF']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);

        // Run d'une autre entreprise → 404 (jamais 403/200).
        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-bf")->assertNotFound();
    }
}
