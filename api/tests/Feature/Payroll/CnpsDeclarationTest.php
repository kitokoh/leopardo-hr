<?php

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
 * CEMAC/CM (issue #1823) — déclaration CNPS mensuelle camerounaise (format
 * DAS, CSV) : structure, plafonnement à 750 000 XAF, ligne TOTAUX, RBAC
 * principal/comptable et isolation tenant.
 *
 * Données : 2 bulletins validés — brut 600 000 XAF (sous plafond) et
 * 900 000 XAF (au-dessus du plafond 750 000).
 *
 * Calculs manuels (CNPS CM, #1821/#1823) :
 *   Slip A (600 000) : base 600 000 · vieillesse sal. 25 200 (4,2 %)
 *     vieillesse pat. 25 200 · famille pat. 42 000 (7 %) · AT pat. 12 000 (2 %)
 *     → total patronal 79 200
 *   Slip B (900 000) : base plafonnée 750 000 · vieillesse sal. 31 500
 *     vieillesse pat. 31 500 · famille pat. 52 500 · AT pat. 18 000 (sur brut)
 *     → total patronal 102 000
 *   TOTAUX : assiette 1 500 000 · assiette plafonnée 1 350 000 ·
 *     vieillesse sal. 56 700 · vieillesse pat. 56 700 · famille pat. 94 500 ·
 *     AT pat. 30 000 · total patronal 181 200
 */
class CnpsDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: PayrollRun, 1: Employee, 2: Employee, 3: PaySlip, 4: PaySlip}
     */
    private function seededRun(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CM',
            'status' => 'validated',
        ]);

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Mbarga',
            'cnps_matricule' => 'CNPS-001',
        ]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Clarisse',
            'last_name' => 'Ngo',
            'cnps_matricule' => 'CNPS-002',
        ]);

        $slipA = $this->slip($run, $employeeA, 600000.0);
        $slipB = $this->slip($run, $employeeB, 900000.0);

        return [$run, $employeeA, $employeeB, $slipA, $slipB];
    }

    private function slip(PayrollRun $run, Employee $employee, float $gross): PaySlip
    {
        return PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'net_salary' => round($gross * 0.75, 2),
            'status' => 'validated',
        ]);
    }

    public function test_csv_structure_and_totals(): void
    {
        [$run] = $this->seededRun();

        $csv = (new CnpsDeclarationGenerator)->generate($run);
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));

        // En-tête + 2 bulletins + ligne TOTAUX.
        $this->assertCount(4, $lines);
        $this->assertSame(
            ['matricule_cnps', 'nom', 'prenom', 'salaire_brut', 'assiette_plafonnee',
                'vieillesse_salariale', 'vieillesse_patronale', 'famille_patronale',
                'at_patronale', 'total_patronal'],
            $lines[0]
        );

        // Slip A (600 000, sous plafond) : assiette = brut.
        $this->assertSame('CNPS-001', $lines[1][0]);
        $this->assertSame('Mbarga', $lines[1][1]);
        $this->assertSame('Jean', $lines[1][2]);
        $this->assertSame('600000.00', $lines[1][3]);
        $this->assertSame('600000.00', $lines[1][4]);
        $this->assertSame('25200.00', $lines[1][5]);
        $this->assertSame('25200.00', $lines[1][6]);
        $this->assertSame('42000.00', $lines[1][7]);
        $this->assertSame('12000.00', $lines[1][8]);
        $this->assertSame('79200.00', $lines[1][9]);

        // Slip B (900 000, au-dessus du plafond) : assiette plafonnée 750 000,
        // AT 2 % sur le brut complet.
        $this->assertSame('CNPS-002', $lines[2][0]);
        $this->assertSame('900000.00', $lines[2][3]);
        $this->assertSame('750000.00', $lines[2][4]);
        $this->assertSame('31500.00', $lines[2][5]);
        $this->assertSame('31500.00', $lines[2][6]);
        $this->assertSame('52500.00', $lines[2][7]);
        $this->assertSame('18000.00', $lines[2][8]);
        $this->assertSame('102000.00', $lines[2][9]);

        // Ligne TOTAUX.
        $this->assertSame('TOTAL', $lines[3][0]);
        $this->assertSame('1500000.00', $lines[3][3]);
        $this->assertSame('1350000.00', $lines[3][4]);
        $this->assertSame('56700.00', $lines[3][5]);
        $this->assertSame('56700.00', $lines[3][6]);
        $this->assertSame('94500.00', $lines[3][7]);
        $this->assertSame('30000.00', $lines[3][8]);
        $this->assertSame('181200.00', $lines[3][9]);
    }

    public function test_totals_method(): void
    {
        [$run] = $this->seededRun();

        $totals = (new CnpsDeclarationGenerator)->totals($run);

        $this->assertSame([
            'employee_count' => 2,
            'assiette' => 1500000.0,
            'assiette_plafonnee' => 1350000.0,
            'vieillesse_salariale' => 56700.0,
            'vieillesse_patronale' => 56700.0,
            'famille_patronale' => 94500.0,
            'at_patronale' => 30000.0,
            'total_patronal' => 181200.0,
        ], $totals);
    }

    public function test_api_principal_can_download_csv(): void
    {
        [$run] = $this->seededRun();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $run->company_id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('matricule_cnps', $response->streamedContent());
        $this->assertStringContainsString('TOTAL', $response->streamedContent());
        $this->assertStringContainsString('181200.00', $response->streamedContent());
    }

    public function test_api_comptable_can_download_csv(): void
    {
        [$run] = $this->seededRun();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $run->company_id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertOk();
    }

    public function test_api_employee_is_forbidden(): void
    {
        [$run] = $this->seededRun();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $run->company_id]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertForbidden();
    }

    public function test_api_cross_tenant_run_is_not_found(): void
    {
        [$run] = $this->seededRun();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/declarations/cnps-cm")->assertNotFound();
    }
}
