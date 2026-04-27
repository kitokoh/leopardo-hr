<?php

namespace App\Services;

use App\Exceptions\SalaryAdvanceNotPendingException;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Support\Carbon;

class SalaryAdvanceService
{
    public function create(Employee $employee, array $data): SalaryAdvance
    {
        $amount = (float) $data['amount'];
        $months = (int) ($data['repayment_months'] ?? 1);

        return SalaryAdvance::create([
            'company_id' => $employee->company_id, 'employee_id' => $employee->id,
            'amount' => $amount, 'reason' => $data['reason'] ?? null,
            'status' => 'pending', 'repayment_months' => $months, 'amount_remaining' => $amount,
        ]);
    }

    public function approve(SalaryAdvance $advance, Employee $approver, array $data = []): SalaryAdvance
    {
        if ($advance->status !== 'pending') throw new SalaryAdvanceNotPendingException();

        $months  = (int) ($data['repayment_months'] ?? $advance->repayment_months ?? 1);
        $monthly = round($advance->amount / $months, 2);
        $plan    = $this->buildPlan($advance->amount, $months, $monthly);

        $advance->update(['status' => 'active', 'approved_by' => $approver->id, 'decision_comment' => $data['decision_comment'] ?? null, 'repayment_months' => $months, 'monthly_deduction' => $monthly, 'amount_remaining' => $advance->amount, 'repayment_plan' => $plan]);

        return $advance->fresh();
    }

    public function reject(SalaryAdvance $advance, Employee $approver, ?string $comment = null): SalaryAdvance
    {
        if ($advance->status !== 'pending') throw new SalaryAdvanceNotPendingException();

        $advance->update(['status' => 'rejected', 'approved_by' => $approver->id, 'decision_comment' => $comment]);

        return $advance->fresh();
    }

    public function cancel(SalaryAdvance $advance): SalaryAdvance
    {
        if ($advance->status !== 'pending') throw new SalaryAdvanceNotPendingException();

        $advance->update(['status' => 'rejected']);

        return $advance->fresh();
    }

    private function buildPlan(float $total, int $months, float $monthly): array
    {
        $plan = []; $remaining = $total;
        $start = Carbon::now()->addMonth()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $amount    = ($i === $months - 1) ? round($remaining, 2) : $monthly;
            $plan[]    = ['month' => $start->copy()->addMonths($i)->format('Y-m'), 'amount' => $amount, 'paid' => false];
            $remaining -= $amount;
        }

        return $plan;
    }
}
