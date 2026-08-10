<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-4 (#1664) — Couverture Payroll ≥ 80 % : PayrollCalculator.
 * Méthodes pures (prorata, heures sup, jours travaillés, indemnité congés,
 * solde de tout compte) + entrées de travail réelles (F-20).
 */
class PayrollCalculatorCoverageTest extends TestCase
{
    use RefreshTenantDatabase;

    private PayrollCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PayrollCalculator;
    }

    // ── computeProratedBase ────────────────────────────────────────────────

    public function test_prorated_base_full_month_returns_full_base(): void
    {
        $this->assertSame(60000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 22.0));
        $this->assertSame(60000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 25.0));
    }

    public function test_prorated_base_zero_days_returns_zero(): void
    {
        $this->assertSame(0.0, $this->calculator->computeProratedBase(60000.0, 22.0, 0.0));
    }

    public function test_prorated_base_partial_month_is_proportional(): void
    {
        $this->assertSame(30000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 11.0));
        $this->assertSame(13636.36, $this->calculator->computeProratedBase(60000.0, 22.0, 5.0));
    }

    public function test_prorated_base_guards_zero_working_days(): void
    {
        $this->assertSame(60000.0, $this->calculator->computeProratedBase(60000.0, 0.0, 0.0));
    }

    // ── computeOvertimePay ─────────────────────────────────────────────────

    public function test_overtime_pay_zero_cases(): void
    {
        $this->assertSame(0.0, $this->calculator->computeOvertimePay(60000.0, 0.0));
        $this->assertSame(0.0, $this->calculator->computeOvertimePay(0.0, 10.0));
    }

    public function test_overtime_pay_standard_hours_at_125(): void
    {
        // Taux horaire 60000 / 173,33 = 346,16 ; 10 h × 346,16 × 1,25 = 4327,00
        $this->assertSame(4327.0, $this->calculator->computeOvertimePay(60000.0, 10.0, 10));
    }

    public function test_overtime_pay_premium_hours_at_150(): void
    {
        // 15 h : 10 h à 1,25 (4327,00) + 5 h à 1,50 (5 × 346,16 × 1,50 = 2596,20)
        $this->assertSame(6923.2, $this->calculator->computeOvertimePay(60000.0, 15.0, 10));
    }

    // ── computeWorkedDays ──────────────────────────────────────────────────

    public function test_worked_days_full_month_overlap(): void
    {
        [$run, $employee] = $this->runAndEmployee('2026-06-01', '2026-06-30');
        $employee->update(['contract_start' => '2025-01-01', 'contract_end' => null]);

        $result = $this->calculator->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $result['working_days']);
        $this->assertSame(22.0, $result['actual_days_worked']);
        $this->assertSame(0.0, $result['overtime_hours']);
    }

    public function test_worked_days_mid_month_hire_is_prorated(): void
    {
        [$run, $employee] = $this->runAndEmployee('2026-06-01', '2026-06-30');
        $employee->update(['contract_start' => '2026-06-10', 'contract_end' => null]);

        $result = $this->calculator->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $result['working_days']);
        // 21 jours de chevauchement sur 30 → 22 × 21/30 = 15,4
        $this->assertSame(15.4, $result['actual_days_worked']);
    }

    public function test_worked_days_contract_end_mid_month(): void
    {
        [$run, $employee] = $this->runAndEmployee('2026-06-01', '2026-06-30');
        $employee->update(['contract_start' => '2025-01-01', 'contract_end' => '2026-06-15']);

        $result = $this->calculator->computeWorkedDays($run, $employee);

        // 15 jours de chevauchement sur 30 → 22 × 15/30 = 11,0
        $this->assertSame(11.0, $result['actual_days_worked']);
    }

    public function test_worked_days_contract_outside_period_is_zero(): void
    {
        [$run, $employee] = $this->runAndEmployee('2026-06-01', '2026-06-30');
        $employee->update(['contract_start' => '2026-08-01', 'contract_end' => null]);

        $result = $this->calculator->computeWorkedDays($run, $employee);

        $this->assertSame(0.0, $result['actual_days_worked']);
    }

    // ── computeLeaveIndemnity ──────────────────────────────────────────────

    public function test_leave_indemnity_zero_days_returns_zero(): void
    {
        $this->assertSame(0.0, $this->calculator->computeLeaveIndemnity(60000.0, 0.0, 22.0, 720000.0));
    }

    public function test_leave_indemnity_maintien_wins_over_dixieme(): void
    {
        // Maintien : 60000 × 5/22 = 13636,36 ; 1/10ᵉ : 720000/10 × 5/30 = 12000
        $this->assertSame(13636.36, $this->calculator->computeLeaveIndemnity(60000.0, 5.0, 22.0, 720000.0));
    }

    public function test_leave_indemnity_dixieme_wins_over_maintien(): void
    {
        // Maintien : 30000 × 5/22 = 6818,18 ; 1/10ᵉ : 720000/10 × 5/30 = 12000
        $this->assertSame(12000.0, $this->calculator->computeLeaveIndemnity(30000.0, 5.0, 22.0, 720000.0));
    }

    public function test_leave_indemnity_guards_zero_working_days_and_accrued(): void
    {
        $this->assertSame(0.0, $this->calculator->computeLeaveIndemnity(60000.0, 5.0, 0.0, 720000.0, 0.0));
    }

    // ── computeFinalSettlement ─────────────────────────────────────────────

    public function test_final_settlement_full_breakdown(): void
    {
        $result = $this->calculator->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 3.0,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 10.0,
            referenceGross12Months: 720000.0,
            severanceMonthsPerYear: 1.0,
            noticeDays: 30.0,
        );

        $this->assertSame(60000.0, $result['prorated_pay']);
        // Maintien 60000×10/22 = 27272,73 vs 1/10ᵉ 720000/10×10/30 = 24000
        $this->assertSame(27272.73, $result['leave_indemnity']);
        // Préavis 30 j : 60000 × 30/22 = 81818,18
        $this->assertSame(81818.18, $result['notice_pay']);
        // Ancienneté 3 ans × 1 mois/an
        $this->assertSame(180000.0, $result['severance']);
        $this->assertSame(349090.91, $result['total']);
    }

    public function test_final_settlement_zero_years_and_notice(): void
    {
        $result = $this->calculator->computeFinalSettlement(60000.0, 0.0, 11.0, 22.0, 0.0, 720000.0);

        $this->assertSame(30000.0, $result['prorated_pay']);
        $this->assertSame(0.0, $result['leave_indemnity']);
        $this->assertSame(0.0, $result['notice_pay']);
        $this->assertSame(0.0, $result['severance']);
        $this->assertSame(30000.0, $result['total']);
    }

    // ── collectWorkInputs (F-20) ───────────────────────────────────────────

    public function test_collect_work_inputs_sums_overtime_and_leave(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'calculated',
        ]);

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-06-10',
            'check_in' => '2026-06-10 08:00:00',
            'check_out' => '2026-06-10 18:00:00',
            'overtime_hours' => 2.5,
            'status' => 'ontime',
        ]);
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-06-11',
            'check_in' => '2026-06-11 08:00:00',
            'check_out' => '2026-06-11 20:00:00',
            'overtime_hours' => 4.0,
            'status' => 'incomplete', // exclu (enum attendance_logs)
        ]);

        /** @var AbsenceType $paidType */
        $paidType = AbsenceType::create(['name' => 'Congé payé', 'code' => 'CONGE_PAYE', 'is_paid' => true]);
        /** @var AbsenceType $unpaidType */
        $unpaidType = AbsenceType::create(['name' => 'Absence injustifiée', 'code' => 'ABSENCE_INJUSTIFIEE', 'is_paid' => false]);

        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $paidType->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
            'days_count' => 3,
            'status' => 'approved',
        ]);
        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $unpaidType->id,
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-21',
            'days_count' => 2,
            'status' => 'approved',
        ]);
        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $paidType->id,
            'start_date' => '2026-06-25',
            'end_date' => '2026-06-26',
            'days_count' => 2,
            'status' => 'pending', // exclu
        ]);

        $inputs = $this->calculator->collectWorkInputs($run, $employee);

        $this->assertSame(2.5, $inputs['overtime_hours']);
        $this->assertSame(3.0, $inputs['paid_leave_days']);
        $this->assertSame(2.0, $inputs['unpaid_leave_days']);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /**
     * @return array{0: PayrollRun, 1: Employee}
     */
    private function runAndEmployee(string $start, string $end): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => $start,
            'period_end' => $end,
            'country_code' => 'DZ',
            'status' => 'calculated',
        ]);

        return [$run, $employee];
    }
}
