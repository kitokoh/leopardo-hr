<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CemacCnpsDeclarationGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CEMAC (#2155) — déclarations sociales CNSS Gabon + Congo-Brazzaville
 * (CSV mensuel).
 *
 * Couvre : structure du CSV, calculs par bulletin calculés À LA MAIN
 * (constitution §III — SMIG, cadre moyen, haut salaire), ligne TOTAUX,
 * protection injection formule CSV (#1922), endpoints protégés (RBAC
 * manager principal/comptable + isolation tenant 404 + 422 mauvais pays).
 */
class CemacCnpsDeclarationTest extends TestCase
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

    // ── CNSS Gabon ─────────────────────────────────────────────────────

    public function test_ga_csv_structure_and_smig_hand_calculated(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §3 + CemacPayrollRules #1824),
        // brut = SMIG GA 150 000 XAF :
        //   retraite salariale = 150 000 × 2,5 % = 3 750,00
        //   retraite patronale = 150 000 × 5,0 % = 7 500,00
        //   famille patronale  = 150 000 × 8,0 % = 12 000,00
        //   AT patronal        = 150 000 × 3,0 % = 4 500,00 (non plafonné)
        //   → salarié 3 750,00 · patronal 24 000,00
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Moussavou',
            'cnps_matricule' => 'CNPS-GA-001',
        ]);
        $this->addValidatedSlip($run, $emp, 150000.0);

        $csv = (new CemacCnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $this->assertCount(3, $lines); // en-tête + 1 bulletin + TOTAUX
        $this->assertStringContainsString('"CNPS-GA-001","Moussavou","Jean"', $lines[1]);
        $this->assertStringContainsString('"150000.00","150000.00","3750.00","7500.00","12000.00","4500.00","3750.00","24000.00"', $lines[1]);
        $this->assertStringContainsString('"TOTAL","1 bulletins"', $lines[2]);
        $this->assertStringContainsString('"24000.00"', $lines[2]);
    }

    public function test_ga_mid_salary_hand_calculated(): void
    {
        // Brut 500 000 (< plafond 3 000 000) :
        //   salarié 12 500 · patronal 25 000 + 40 000 + 15 000 = 80 000
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Marie', 'last_name' => 'Obame', 'cnps_matricule' => 'CNPS-GA-002']);
        $this->addValidatedSlip($run, $emp, 500000.0);

        $lines = array_values(array_filter(explode("\n", (new CemacCnpsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"12500.00","25000.00","40000.00","15000.00","12500.00","80000.00"', $lines[1]);
    }

    public function test_ga_high_salary_cap_applied_hand_calculated(): void
    {
        // Brut 4 000 000 > plafond retraite/famille 3 000 000 :
        //   retraite base 3 000 000 → salarié 75 000 · patronal 150 000
        //   + 240 000 + AT 4 000 000 × 3,0 % = 120 000 → 510 000
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Paul', 'last_name' => 'Nguema', 'cnps_matricule' => 'CNPS-GA-003']);
        $this->addValidatedSlip($run, $emp, 4000000.0);

        $lines = array_values(array_filter(explode("\n", (new CemacCnpsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"4000000.00","3000000.00","75000.00","150000.00","240000.00","120000.00","75000.00","510000.00"', $lines[1]);
    }

    // ── CNSS Congo-Brazzaville ─────────────────────────────────────────

    public function test_cg_csv_structure_and_smig_hand_calculated(): void
    {
        // Calcul manuel (CG_COMPLIANCE.md §3 + CemacPayrollRules #1824),
        // brut = SMIG CG 90 000 XAF :
        //   retraite salariale = 90 000 × 4,0 % = 3 600,00
        //   retraite patronale = 90 000 × 8,0 % = 7 200,00
        //   famille patronale  = 90 000 × 10,0 % = 9 000,00
        //   AT patronal        = 90 000 × 3,0 % = 2 700,00 (non plafonné)
        //   → salarié 3 600,00 · patronal 18 900,00
        $run = $this->makeRun('CG');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Claude',
            'last_name' => 'Makaya',
            'cnps_matricule' => 'CNPS-CG-001',
        ]);
        $this->addValidatedSlip($run, $emp, 90000.0);

        $csv = (new CemacCnpsDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('"CNPS-CG-001","Makaya","Claude"', $lines[1]);
        $this->assertStringContainsString('"90000.00","90000.00","3600.00","7200.00","9000.00","2700.00","3600.00","18900.00"', $lines[1]);
        $this->assertStringContainsString('"TOTAL","1 bulletins"', $lines[2]);
    }

    public function test_cg_high_salary_cap_applied_hand_calculated(): void
    {
        // Brut 3 000 000 > plafond retraite/famille 2 500 000 :
        //   retraite base 2 500 000 → salarié 100 000 · patronal 200 000
        //   + 250 000 + AT 3 000 000 × 3,0 % = 90 000 → 540 000
        $run = $this->makeRun('CG');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Sylvie', 'last_name' => 'Bemba', 'cnps_matricule' => 'CNPS-CG-002']);
        $this->addValidatedSlip($run, $emp, 3000000.0);

        $lines = array_values(array_filter(explode("\n", (new CemacCnpsDeclarationGenerator)->generate($run)), fn ($l) => trim($l) !== ''));

        $this->assertStringContainsString('"3000000.00","2500000.00","100000.00","200000.00","250000.00","90000.00","100000.00","540000.00"', $lines[1]);
    }

    // ── Sécurité & contrats ────────────────────────────────────────────

    public function test_csv_injection_neutralized(): void
    {
        // #1922 : un nom commençant par « + » ne doit pas devenir une
        // formule Excel.
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '+IMPORTXML("http://evil.example")',
            'last_name' => 'Hacker',
            'cnps_matricule' => '+1+1',
        ]);
        $this->addValidatedSlip($run, $emp, 150000.0);

        $csv = (new CemacCnpsDeclarationGenerator)->generate($run);

        $this->assertStringContainsString("'+IMPORTXML", $csv);
        $this->assertStringContainsString("'+1+1", $csv);
        $this->assertStringNotContainsString('"+IMPORTXML', $csv);
    }

    public function test_endpoint_ga_returns_csv_for_manager(): void
    {
        $run = $this->makeRun('GA');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Jean', 'last_name' => 'Moussavou', 'cnps_matricule' => 'CNPS-GA-001']);
        $this->addValidatedSlip($run, $emp, 150000.0);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-ga");
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('CNSS_GA_', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('"CNPS-GA-001"', $response->streamedContent());
    }

    public function test_endpoint_cg_returns_csv_for_manager(): void
    {
        $run = $this->makeRun('CG');
        /** @var Employee $emp */
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Claude', 'last_name' => 'Makaya', 'cnps_matricule' => 'CNPS-CG-001']);
        $this->addValidatedSlip($run, $emp, 90000.0);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-cg");
        $response->assertOk();
        $this->assertStringContainsString('CNSS_CG_', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('"CNPS-CG-001"', $response->streamedContent());
    }

    public function test_endpoint_rejects_wrong_country_run(): void
    {
        $run = $this->makeRun('CM'); // run CM, pas GA/CG

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-ga")->assertStatus(422);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-cg")->assertStatus(422);
    }

    public function test_endpoint_forbidden_for_employee(): void
    {
        $run = $this->makeRun('GA');
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-ga")->assertStatus(403);
    }

    public function test_endpoint_tenant_isolation(): void
    {
        $run = $this->makeRun('GA');
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'GA', 'currency' => 'XAF']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);

        // Run d'une autre entreprise → 404 (jamais 403/200).
        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnss-ga")->assertNotFound();
    }
}
