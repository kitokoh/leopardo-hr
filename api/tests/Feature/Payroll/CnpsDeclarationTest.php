<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1823 — déclaration CNPS Cameroun (DAS mensuelle).
 *
 * Vérifie : structure CSV + ligne TOTAUX, plafonnement 750 000 XAF,
 * RBAC principal/comptable, isolation tenant (404 cross-tenant).
 * Référence : docs/payroll/CM_COMPLIANCE.md §3.
 */
class CnpsDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_csv_structure_and_totals(): void
    {
        [$company, $manager, $employee] = $this->cnpsActors();
        /** @var Employee $secondEmployee */
        $secondEmployee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Njoya',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-CNPS-002',
            'status' => 'active',
        ]);
        $run = $this->makeRun($company, '2026-05-01');
        $this->validatedSlip($run, $employee, 200000.0, 'EMP-CNPS-001');
        $this->validatedSlip($run, $secondEmployee, 400000.0, 'EMP-CNPS-002');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnps-cm');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = (string) $response->getContent();
        $header = '"immatriculation_cnps","nom","prenom","salaire_brut","assiette_plafonnee",'
            .'"vieillesse_salariale","vieillesse_patronale","famille_patronale","at_patronale","total_patronal"';
        $this->assertStringContainsString($header, $csv);

        // Bulletin 200 000 (sous plafond) : 4,2 % / 4,2 % / 7,0 % / 2,0 %
        $this->assertStringContainsString('"EMP-CNPS-001","Doe","Amina","200000.00","200000.00","8400.00","8400.00","14000.00","4000.00","26400.00"', $csv);
        // Bulletin 400 000 : mêmes taux sur 400 000 (toujours sous plafond)
        $this->assertStringContainsString('"EMP-CNPS-002","Njoya","Karim","400000.00","400000.00","16800.00","16800.00","28000.00","8000.00","52800.00"', $csv);
        // Ligne TOTAUX : assiette 600 000 · salariale 25 200 · patronale 79 200
        $this->assertStringContainsString('"TOTAUX","2 bulletins","","600000.00","25200.00","25200.00","42000.00","12000.00","79200.00"', $csv);
    }

    public function test_cap_750k_applied_in_declaration(): void
    {
        [$company, $manager, $employee] = $this->cnpsActors();
        $run = $this->makeRun($company, '2026-05-01');
        $this->validatedSlip($run, $employee, 1000000.0, 'EMP-CNPS-003');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnps-cm');

        $response->assertOk();
        $csv = (string) $response->getContent();

        // Brut 1 000 000 → assiette plafonnée à 750 000 :
        //   vieillesse salariale 31 500 · vieillesse patronale 31 500
        //   famille 52 500 · AT 20 000 (non plafonné) · total patronal 104 000
        $this->assertStringContainsString('"EMP-CNPS-003","Doe","Amina","1000000.00","750000.00","31500.00","31500.00","52500.00","20000.00","104000.00"', $csv);
        $this->assertStringContainsString('"TOTAUX","1 bulletins","","750000.00","31500.00","31500.00","52500.00","20000.00","104000.00"', $csv);
    }

    public function test_rbac_employee_and_plain_manager_blocked(): void
    {
        [$company, , $employee] = $this->cnpsActors();
        $run = $this->makeRun($company, '2026-05-01');
        $this->validatedSlip($run, $employee, 200000.0, 'EMP-CNPS-004');

        /** @var Employee $plainManager */
        $plainManager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh', // RH ≠ principal/comptable
            'email' => fake()->unique()->safeEmail(),
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnps-cm')->assertForbidden();

        Sanctum::actingAs($plainManager);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnps-cm')->assertForbidden();
    }

    public function test_cross_tenant_declaration_blocked(): void
    {
        [$company, $manager, $employee] = $this->cnpsActors();
        $this->validatedSlip($this->makeRun($company, '2026-05-01'), $employee, 200000.0, 'EMP-CNPS-005');

        [$otherCompany, $otherManager] = $this->cnpsActors();
        $otherRun = $this->makeRun($otherCompany, '2026-05-01');
        $this->validatedSlip($otherRun, $otherManager, 300000.0, 'EMP-CNPS-006');

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll-runs/'.$otherRun->id.'/declarations/cnps-cm')->assertNotFound();
    }

    public function test_employee_cnps_matricule_is_optional_and_falls_back_to_matricule(): void
    {
        [$company, $manager, $employee] = $this->cnpsActors([], ['matricule' => 'INTERNE-42']);
        $run = $this->makeRun($company, '2026-05-01');
        $this->validatedSlip($run, $employee, 100000.0, null);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/cnps-cm');
        $response->assertOk();

        $this->assertStringContainsString('"INTERNE-42","Doe","Amina","100000.00"', (string) $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $companyOverrides
     * @param  array<string, mixed>  $employeeOverrides
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function cnpsActors(array $companyOverrides = [], array $employeeOverrides = []): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'name' => 'Leopardo CM',
            'metadata' => ['tax_id' => 'CM-NIU-00123'],
        ], $companyOverrides));

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'email' => fake()->unique()->safeEmail(),
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-CNPS-001',
            'status' => 'active',
            'contract_start' => '2025-01-10',
            'contract_type' => 'CDI',
        ], $employeeOverrides));

        return [$company, $manager, $employee];
    }

    private function makeRun(Company $company, string $periodStart): PayrollRun
    {
        return PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'CM',
            'period_start' => $periodStart,
            'period_end' => Carbon::parse($periodStart)->endOfMonth()->toDateString(),
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 0.0,
            'total_deductions' => 0.0,
            'total_net' => 0.0,
            'validated_at' => now(),
        ]);
    }

    private function validatedSlip(
        PayrollRun $run,
        Employee $employee,
        float $grossSalary,
        ?string $cnpsMatricule,
    ): PaySlip {
        if ($cnpsMatricule !== null) {
            $employee->update(['cnps_matricule' => $cnpsMatricule]);
        }

        return PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $grossSalary,
            'total_deductions' => round($grossSalary * 0.1, 2),
            'net_salary' => round($grossSalary * 0.9, 2),
            'status' => 'validated',
        ]);
    }
}
