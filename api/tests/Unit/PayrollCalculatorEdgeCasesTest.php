<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * S-4 (#1664) — Edge cases du noyau paie (calcul pur, sans DB).
 *
 * Complète la couverture des méthodes pures de PayrollCalculator :
 * computeProratedBase, computeOvertimePay, computeLeaveIndemnity,
 * computeFinalSettlement (toutes les branches) et computeComponentAmount
 * (privé — appelé via réflexion, branches fixed/percentage_of_base/
 * percentage_of_gross/default).
 */
class PayrollCalculatorEdgeCasesTest extends TestCase
{
    private PayrollCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PayrollCalculator();
    }

    // ── computeProratedBase ────────────────────────────────────────────────

    public function test_prorated_base_full_month(): void
    {
        $this->assertSame(60000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 22.0));
    }

    public function test_prorated_base_half_month(): void
    {
        $this->assertSame(30000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 11.0));
    }

    public function test_prorated_base_zero_actual_days(): void
    {
        $this->assertSame(0.0, $this->calculator->computeProratedBase(60000.0, 22.0, 0.0));
    }

    public function test_prorated_base_actual_days_exceed_working_days(): void
    {
        // Branche "mois complet" : on ne dépasse jamais la base.
        $this->assertSame(60000.0, $this->calculator->computeProratedBase(60000.0, 22.0, 30.0));
    }

    public function test_prorated_base_zero_base_salary(): void
    {
        $this->assertSame(0.0, $this->calculator->computeProratedBase(0.0, 22.0, 11.0));
    }

    // ── computeOvertimePay ─────────────────────────────────────────────────

    public function test_overtime_pay_zero_hours(): void
    {
        $this->assertSame(0.0, $this->calculator->computeOvertimePay(60000.0, 0.0));
    }

    public function test_overtime_pay_ten_hours_default_rate(): void
    {
        // Golden F-05 : 60000 / 173,33 h × 10 h × 1,25 = 4327,00 DZD
        $this->assertSame(4327.0, $this->calculator->computeOvertimePay(60000.0, 10.0));
    }

    public function test_overtime_pay_custom_standard_rate_hours(): void
    {
        // 10 h à 25 % + 10 h à 50 % = 4327,00 + 5192,40 = 9519,40 DZD
        $this->assertSame(9519.4, $this->calculator->computeOvertimePay(60000.0, 20.0, 10));
    }

    public function test_overtime_pay_zero_base(): void
    {
        $this->assertSame(0.0, $this->calculator->computeOvertimePay(0.0, 10.0));
    }

    // ── computeLeaveIndemnity ──────────────────────────────────────────────

    public function test_leave_indemnity_chooses_maintenance_when_higher(): void
    {
        // maintien de salaire : 60000 * (15/22) = 40909,09
        // 1/10e : 720000 * 15/30 / 10 = 36000
        $result = $this->calculator->computeLeaveIndemnity(
            monthlyBase: 60000.0,
            leaveDays: 15.0,
            workingDays: 22.0,
            referenceGross12Months: 720000.0,
        );
        $this->assertSame(40909.09, $result);
    }

    public function test_leave_indemnity_chooses_tenth_when_higher(): void
    {
        // 1/10e : 900000 * 30/30 / 10 = 90000 > maintien 60000
        $result = $this->calculator->computeLeaveIndemnity(
            monthlyBase: 60000.0,
            leaveDays: 30.0,
            workingDays: 22.0,
            referenceGross12Months: 900000.0,
        );
        $this->assertSame(90000.0, $result);
    }

    public function test_leave_indemnity_zero_days(): void
    {
        $result = $this->calculator->computeLeaveIndemnity(60000.0, 0.0, 22.0, 720000.0);
        $this->assertSame(0.0, $result);
    }

    public function test_leave_indemnity_zero_reference_gross(): void
    {
        $result = $this->calculator->computeLeaveIndemnity(60000.0, 15.0, 22.0, 0.0);
        $this->assertSame(40909.09, $result); // maintien uniquement
    }

    // ── computeFinalSettlement ─────────────────────────────────────────────

    public function test_final_settlement_with_notice_and_severance(): void
    {
        $result = $this->calculator->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 3.0,
            proratedDays: 11.0,
            workingDays: 22.0,
            unpaidLeaveDays: 5.0,
            referenceGross12Months: 720000.0,
            severanceMonthsPerYear: 1.0,
            noticeDays: 30.0,
        );

        $this->assertArrayHasKey('prorated_pay', $result);
        $this->assertArrayHasKey('leave_indemnity', $result);
        $this->assertArrayHasKey('notice_pay', $result);
        $this->assertArrayHasKey('severance', $result);
        $this->assertArrayHasKey('total', $result);
        // préavis 30 jours calendaires : 60000 * (30/22) = 81818,18
        $this->assertSame(81818.18, $result['notice_pay']);
        // ancienneté 3 ans : 60000 * 3 * 1 = 180000
        $this->assertSame(180000.0, $result['severance']);
    }

    public function test_final_settlement_without_notice_or_severance(): void
    {
        $result = $this->calculator->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 0.0,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 0.0,
        );

        $this->assertSame(0.0, $result['notice_pay']);
        $this->assertSame(0.0, $result['severance']);
        $this->assertSame(60000.0, $result['prorated_pay']);
    }

    public function test_final_settlement_zero_monthly_base(): void
    {
        $result = $this->calculator->computeFinalSettlement(0.0, 1.0, 11.0, 22.0, 0.0, 0.0);
        $this->assertSame(0.0, $result['total']);
    }

    // ── computeComponentAmount (privé, via réflexion) ──────────────────────

    private function componentAmount(SalaryComponent $component, float $base, float $gross): float
    {
        $method = new ReflectionMethod(PayrollCalculator::class, 'computeComponentAmount');
        $method->setAccessible(true);

        return (float) $method->invoke($this->calculator, $component, $base, $gross);
    }

    public function test_component_amount_fixed(): void
    {
        $component = new SalaryComponent(['calculation_type' => 'fixed', 'amount' => 1234.567]);
        $this->assertSame(1234.57, $this->componentAmount($component, 60000.0, 60000.0));
    }

    public function test_component_amount_percentage_of_base(): void
    {
        $component = new SalaryComponent(['calculation_type' => 'percentage_of_base', 'percentage' => 12.5]);
        $this->assertSame(7500.0, $this->componentAmount($component, 60000.0, 60000.0));
    }

    public function test_component_amount_percentage_of_gross(): void
    {
        $component = new SalaryComponent(['calculation_type' => 'percentage_of_gross', 'percentage' => 10.0]);
        $this->assertSame(8000.0, $this->componentAmount($component, 60000.0, 80000.0));
    }

    public function test_component_amount_unknown_type_defaults_to_zero(): void
    {
        $component = new SalaryComponent(['calculation_type' => 'formula', 'amount' => 100.0]);
        $this->assertSame(0.0, $this->componentAmount($component, 60000.0, 60000.0));
    }
}
