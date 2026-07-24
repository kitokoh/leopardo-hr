<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Exceptions\SalaryAdvanceNotPendingException;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SalaryAdvanceService
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {}

    public function create(Employee $employee, array $data): SalaryAdvance
    {
        $amount = (float) $data['amount'];
        $months = (int) ($data['repayment_months'] ?? 1);

        // PA2-PAY-002: snapshot the tenant currency at creation time so the
        // advance receipt stays historically accurate even if the
        // company's currency setting changes later.
        $currency = $data['currency'] ?? $employee->company?->currency ?? 'DZD';

        return SalaryAdvance::create([
            'company_id' => $employee->company_id, 'employee_id' => $employee->id,
            'amount' => $amount, 'currency' => $currency, 'reason' => $data['reason'] ?? null,
            'status' => 'pending', 'repayment_months' => $months, 'amount_remaining' => $amount,
        ]);
    }

    public function approve(SalaryAdvance $advance, Employee $approver, array $data = []): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException;
        }

        $months = (int) ($data['repayment_months'] ?? $advance->repayment_months ?? 1);
        $monthly = round($advance->amount / $months, 2);
        $plan = $this->buildPlan($advance->amount, $months, $monthly);

        $advance->update(['status' => 'active', 'approved_by' => $approver->id, 'decision_comment' => $data['decision_comment'] ?? null, 'repayment_months' => $months, 'monthly_deduction' => $monthly, 'amount_remaining' => $advance->amount, 'repayment_plan' => $plan]);
        $advance = $advance->fresh();

        $this->notify($advance, 'salary_advance_manager_approved');

        return $advance;
    }

    public function reject(SalaryAdvance $advance, Employee $approver, ?string $comment = null): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException;
        }

        $advance->update(['status' => 'rejected', 'approved_by' => $approver->id, 'decision_comment' => $comment]);
        $advance = $advance->fresh();

        $this->notify($advance, 'salary_advance_rejected');

        return $advance;
    }

    /**
     * Notify the advance owner about a status change (PA2-PAY-008).
     * Notification failures must never break the advance workflow.
     */
    public function notify(SalaryAdvance $advance, string $templateKey): void
    {
        $employee = $advance->employee ?? Employee::query()->withoutGlobalScopes()->find($advance->employee_id);

        if ($employee instanceof Employee) {
            $this->notifyRecipient($advance, $employee, $templateKey);
        }
    }

    /**
     * Notify an explicit recipient (e.g. the manager who declared payment,
     * once the employee confirms reception) about the advance.
     */
    public function notifyRecipient(SalaryAdvance $advance, Employee $recipient, string $templateKey): void
    {
        try {
            $this->communication->notifyEmployee($recipient, $templateKey, [
                'salary_advance_id' => $advance->id,
                'payment_reference' => $advance->payment_reference,
            ]);
        } catch (Throwable $exception) {
            Log::warning('salary-advance: failed to notify recipient', [
                'salary_advance_id' => $advance->id,
                'recipient_id' => $recipient->id,
                'template' => $templateKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function cancel(SalaryAdvance $advance): SalaryAdvance
    {
        if ($advance->status !== 'pending') {
            throw new SalaryAdvanceNotPendingException;
        }

        $advance->update(['status' => 'rejected']);

        return $advance->fresh();
    }

    private function buildPlan(float $total, int $months, float $monthly): array
    {
        $plan = [];
        $remaining = $total;
        $start = Carbon::now()->addMonth()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $amount = ($i === $months - 1) ? round($remaining, 2) : $monthly;
            $plan[] = ['month' => $start->copy()->addMonths($i)->format('Y-m'), 'amount' => $amount, 'paid' => false];
            $remaining -= $amount;
        }

        return $plan;
    }
}
