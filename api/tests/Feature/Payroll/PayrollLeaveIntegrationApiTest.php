<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5245 — « Affichage dans la simulation du run (détail par employé) » :
 * le détail des entrées de travail (congés payés pris, congés sans solde,
 * jours fériés payés, prorata) et les soldes de congés sont exposés par
 * l'API du run, en isolation tenant stricte.
 */
class PayrollLeaveIntegrationApiTest extends TestCase
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
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = $companyB;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $this->companyA->id]);
        $this->managerA = $managerA;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $this->companyB->id]);
        $this->managerB = $managerB;
    }

    /**
     * @return array{calculated: PayrollRun, employee: Employee, structure: SalaryStructure}
     */
    private function seedCalculatedRun(Company $company, string $periodStart = '2026-03-01', string $periodEnd = '2026-03-31'): array
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_type' => 'CDI',
            'contract_start' => '2025-01-01',
            'salary_base' => 60000,
            'salary_type' => 'fixed',
        ]);

        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille A',
            'code' => 'GRID-A',
            'base_salary' => 60000,
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $paidType = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $paidType->id,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-11',
            'days_count' => 2,
            'status' => 'approved',
            'reason' => 'Test #5245',
        ]);

        // Soldes annuels 2026 : 30 acquis, 2 pris, 1 en attente → 27 restants.
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $paidType->id,
            'balance' => 27,
            'used' => 2,
            'pending' => 1,
            'year' => 2026,
        ]);

        // 1 férié entreprise → mars 2026 : 31 j − 8 repos (ven/sam) − 1 férié
        // = 22 jours ouvrés ; 20 pointés + 2 j de congé payé → 22/22 payés.
        PublicHoliday::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Fête d\'entreprise (test)',
            'date' => '2026-03-19',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'company',
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        $calculated = app(PayrollCalculator::class)->calculateRun($run);

        return compact('calculated', 'employee', 'structure');
    }

    public function test_run_pay_slips_expose_leave_detail_per_employee(): void
    {
        ['calculated' => $run, 'employee' => $employee] = $this->seedCalculatedRun($this->companyA);

        Sanctum::actingAs($this->managerA, ['*']);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips?per_page=50");

        $response->assertOk();

        $slip = collect((array) $response->json('data'))
            ->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($slip, 'Bulletin de l\'employé absent de la réponse');

        // Détail des entrées de travail (issue #5245). JSON encode les
        // floats entiers sans décimale (22 vs 22.0) — comparaison en float.
        $this->assertSame(22.0, (float) $slip['attendance']['working_days']);
        $this->assertSame(20.0, (float) $slip['attendance']['actual_days_worked']);
        $this->assertSame(2.0, (float) $slip['attendance']['paid_leave_days']);
        $this->assertSame(0.0, (float) $slip['attendance']['unpaid_leave_days']);
        $this->assertSame(1.0, (float) $slip['attendance']['public_holiday_days']);
        $this->assertSame(60000.0, (float) $slip['gross_salary']);

        // Soldes de congés annuels (agrégés types payés).
        $this->assertSame([
            'acquired' => 30.0,
            'used' => 2.0,
            'pending' => 1.0,
            'remaining' => 27.0,
        ], array_map('floatval', (array) $slip['attendance']['leave_balance']));

        // Rétro-compatibilité : champs historiques toujours présents au top level.
        $this->assertSame(22.0, (float) $slip['working_days']);
        $this->assertSame(20.0, (float) $slip['actual_days_worked']);
    }

    public function test_run_pay_slips_cross_tenant_isolation(): void
    {
        ['calculated' => $run] = $this->seedCalculatedRun($this->companyA);

        Sanctum::actingAs($this->managerB, ['*']);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips")->assertNotFound();
    }

    public function test_pay_slips_list_with_run_filter_exposes_leave_balance(): void
    {
        ['calculated' => $run, 'employee' => $employee] = $this->seedCalculatedRun($this->companyA);

        Sanctum::actingAs($this->managerA, ['*']);

        $response = $this->getJson("/api/v1/pay-slips?payroll_run_id={$run->id}");

        $response->assertOk();

        $slip = collect((array) $response->json('data'))
            ->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($slip);
        $this->assertSame(2.0, (float) $slip['attendance']['paid_leave_days']);
        $this->assertSame([
            'acquired' => 30.0,
            'used' => 2.0,
            'pending' => 1.0,
            'remaining' => 27.0,
        ], array_map('floatval', (array) $slip['attendance']['leave_balance']));
    }

    public function test_pay_slip_show_keeps_backward_compatible_shape(): void
    {
        ['calculated' => $run, 'employee' => $employee] = $this->seedCalculatedRun($this->companyA);

        $slip = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        Sanctum::actingAs($this->managerA, ['*']);

        $response = $this->getJson("/api/v1/pay-slips/{$slip->id}");

        $response->assertOk();
        $this->assertSame(60000.0, (float) $response->json('data.gross_salary'));
        $this->assertSame(2.0, (float) $response->json('data.attendance.paid_leave_days'));
        // Sans attachment (show), leave_balance reste null — aucun N+1 introduit.
        $this->assertNull($response->json('data.attendance.leave_balance'));
    }
}
