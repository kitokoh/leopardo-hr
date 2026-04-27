<?php

namespace App\Services;

use App\Exceptions\SalaryAdvanceNotPendingException;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Support\Carbon;

class SalaryAdvanceService
{
    /**
     * Create a new salary advance request (status: pending).
     */
    public function create(Employee $employee, array $data): SalaryAdvance
    {
        $repaymentMonths = $data['repayment_months'] ?? 1;
        $amount = (float) $data['amount'];

        return SalaryAdvance::create([
            'company_id'       => $employee->company_id,
            'employee_id'      => $employee->id,
            'amount'           => $amount,
            'reason'           => $data['reason'] ?? null,
            'status'           => 'pending',
            'repayment_months' => $repaymentMonths,
            'amount_remaining' => $amount,
        ]);
    }

    /**
     * Approve a pending salary advance.
     * Calculates monthly_deduction and builds repayment_plan.
     *
     * @throws SalaryAdvanceNotPendingException
     */
    public function approve(SalaryAdvance $advance, Employee $approver, array $data = []): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException();
        }

        $repaymentMonths = $data['repayment_months'] ?? $advance->repayment_months ?? 1;
        $monthlyDeduction = round($advance->amount / $repaymentMonths, 2);

        // Build repayment plan starting next month
        $repaymentPlan = $this->buildRepaymentPlan($advance->amount, $repaymentMonths, $monthlyDeduction);

        $advance->update([
            'status'            => 'active',
            'approved_by'       => $approver->id,
            'decision_comment'  => $data['decision_comment'] ?? null,
            'repayment_months'  => $repaymentMonths,
            'monthly_deduction' => $monthlyDeduction,
            'amount_remaining'  => $advance->amount,
            'repayment_plan'    => $repaymentPlan,
        ]);

        return $advance->fresh();
    }

    /**
     * Reject a pending salary advance.
     *
     * @throws SalaryAdvanceNotPendingException
     */
    public function reject(SalaryAdvance $advance, Employee $approver, ?string $comment = null): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException();
        }

        $advance->update([
            'status'           => 'rejected',
            'approved_by'      => $approver->id,
            'decision_comment' => $comment,
        ]);

        return $advance->fresh();
    }

    /**
     * Cancel a pending salary advance (by the employee themselves).
     *
     * @throws SalaryAdvanceNotPendingException
     */
    public function cancel(SalaryAdvance $advance): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException();
        }

        $advance->update(['status' => 'rejected']);

        return $advance->fresh();
    }

    /**
     * Build a monthly repayment plan array.
     */
    private function buildRepaymentPlan(float $totalAmount, int $months, float $monthlyDeduction): array
    {
        $plan = [];
        $remaining = $totalAmount;
        $startMonth = Carbon::now()->addMonth()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $deduction = ($i === $months - 1)
                ? round($remaining, 2)  // Last month: pay remainder to avoid rounding drift
                : $monthlyDeduction;

            $plan[] = [
                'month'  => $startMonth->copy()->addMonths($i)->format('Y-m'),
                'amount' => $deduction,
                'paid'   => false,
            ];

            $remaining -= $deduction;
        }

        return $plan;
    }
}
