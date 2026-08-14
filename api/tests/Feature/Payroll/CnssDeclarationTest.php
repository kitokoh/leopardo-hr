<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1830 — déclaration CNSS mensuelle Côte d'Ivoire (CSV).
 *
 * Vérifie : structure CSV + ligne TOTAUX, plafonnement CNSS 1 647 315 XOF,
 * RBAC principal/comptable, pays ≠ CI → 422, isolation tenant (404).
 * Référence : docs/payroll/CI_COMPLIANCE.md §2.
 */
class CnssDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function ciActors(): array
    {
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Kouassi',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-CI-001',
            'cnss_ci_matricule' => 'CNSS-CI-001',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    private function makeRun(Company $company, string $periodStart = '2026-05-01', string $country = 'CI'): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => $periodStart,
            'period_end' => '2026-05-31',
            'country_code' => $country,
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);
    }

    private function validatedSlip(PayrollRun $run, Employee $employee, float $gross): void
    {
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
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

    public function test_ci_csv_structure_and_totals(): void
    {
        [$company, $manager, $employee] = $this->ciActors();
        $secondEmployee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Yao',
            'last_name' => 'NGuessan',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-CI-002',
            'cnss_ci_matricule' => 'CNSS-CI-002',
            'status' => 'active',
        ]);
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);
        $this->validatedSlip($run, $secondEmployee, 400000.0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = (string) $response->getContent();
        $header = '"matricule_cnss","nom","prenom","salaire_brut","assiette_plafonnee","retraite_salariale",'
            .'"retraite_patronale","famille_patronale","at_patronale","total_salarial","total_patronal"';
        $this->assertStringContainsString($header, $csv);

        // Bulletin 200 000 (sous plafond) : 3,2 % / 4,5 % / 5,75 % / 2,0 %
        //   salariale 6 400 · patronale 9 000 + 11 500 + 4 000 = 24 500
        $this->assertStringContainsString('"CNSS-CI-001","Kouassi","Amina","200000.00","200000.00","6400.00","9000.00","11500.00","4000.00","6400.00","24500.00"', $csv);
        // Bulletin 400 000 : salariale 12 800 · patronale 18 000 + 23 000 + 8 000 = 49 000
        $this->assertStringContainsString('"CNSS-CI-002","NGuessan","Yao","400000.00","400000.00","12800.00","18000.00","23000.00","8000.00","12800.00","49000.00"', $csv);
        // Ligne TOTAUX : assiette 600 000 · salariale 19 200 · patronale 27 000 + 34 500 + 12 000 = 73 500
        $this->assertStringContainsString('"TOTAUX","2 bulletins","","600000.00","600000.00","19200.00","27000.00","34500.00","12000.00","19200.00","73500.00"', $csv);
    }

    public function test_ci_cap_1647315_applied(): void
    {
        [$company, $manager, $employee] = $this->ciActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 2000000.0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci');

        $response->assertOk();
        $csv = (string) $response->getContent();

        // Brut 2 000 000 → assiette plafonnée à 1 647 315 :
        //   salariale 52 714,08 · retraite patronale 74 129,18
        //   famille 94 720,61 · AT 40 000 (non plafonné) · total patronal 208 849,79
        $this->assertStringContainsString('"CNSS-CI-001","Kouassi","Amina","2000000.00","1647315.00","52714.08","74129.18","94720.61","40000.00","52714.08","208849.79"', $csv);
    }

    public function test_ci_wrong_country_returns_422(): void
    {
        [$company, $manager, $employee] = $this->ciActors();
        // Run DZ : la déclaration CNSS CI ne s'applique pas.
        $run = $this->makeRun($company, '2026-05-01', 'DZ');
        $this->validatedSlip($run, $employee, 200000.0);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci')->assertStatus(422);
    }

    public function test_ci_rbac_employee_and_plain_manager_blocked(): void
    {
        [$company, , $employee] = $this->ciActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);

        $plainManager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh', // RH ≠ principal/comptable
            'email' => fake()->unique()->safeEmail(),
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci')->assertForbidden();

        Sanctum::actingAs($plainManager);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci')->assertForbidden();
    }

    public function test_ci_cross_tenant_declaration_blocked(): void
    {
        [$company, , $employee] = $this->ciActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);

        [$otherCompany, $otherManager] = $this->ciActors();
        $otherRun = $this->makeRun($otherCompany);

        Sanctum::actingAs($otherManager);

        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnss-ci')->assertNotFound();
        $this->getJson('/api/v1/payroll-runs/'.$otherRun->id.'/declarations/cnss-ci')->assertOk();
    }
}
