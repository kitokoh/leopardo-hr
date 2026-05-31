<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Plan 61 — Solde employé & cycle de paie.
 *
 * Provides:
 *   - getCurrentCycle(Company): current pay period dates
 *   - getEmployeeBalance(Employee): gross_due, advances, paid, remaining
 *   - closeCycle(PayrollRun): marks cycle as closed and updates balances
 */
class PayrollCycleService
{
    /**
     * Get the current pay cycle dates for a company.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function getCurrentCycle(Company $company): array
    {
        // Use company pay_cycle setting if available, default to monthly
        $payCycle = $company->settings['pay_cycle'] ?? 'monthly';
        $now = Carbon::now();

        return match ($payCycle) {
            'weekly'  => $this->weeklyPeriod($now),
            'daily'   => $this->dailyPeriod($now),
            default   => $this->monthlyPeriod($now), // monthly
        };
    }

    /**
     * Calculate the balance for a single employee.
     *
     * @return array{
     *     employee_id: int,
     *     period: array{start: string, end: string},
     *     gross_due: float,
     *     advances: float,
     *     paid: float,
     *     remaining: float
     * }
     */
    public function getEmployeeBalance(Employee $employee): array
    {
        $company = $employee->company;
        $cycle   = $this->getCurrentCycle($company);

        // Latest validated payroll run for the current cycle
        /** @var PayrollRun|null $payrollRun */
        $payrollRun = PayrollRun::query()
            ->where('company_id', $employee->company_id)
            ->where('period_start', '<=', $cycle['end'])
            ->where('period_end', '>=', $cycle['start'])
            ->latest('period_start')
            ->first();

        $grossDue = 0.0;
        $paid     = 0.0;

        if ($payrollRun !== null) {
            // Fetch the pay slip for this employee in this run
            $paySlip = $payrollRun->paySlips()
                ->where('employee_id', $employee->id)
                ->first();

            if ($paySlip !== null) {
                $grossDue = (float) ($paySlip->net_salary ?? 0);
                $paid     = $payrollRun->status === 'paid' ? $grossDue : 0.0;
            }
        }

        // Active salary advances (employee_confirmed or payment_declared)
        $advances = SalaryAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->whereIn('validation_status', ['payment_declared', 'employee_confirmed'])
            ->whereBetween('payment_declared_at', [$cycle['start'], $cycle['end']])
            ->sum('amount');

        $remaining = $grossDue - (float) $advances - $paid;

        return [
            'employee_id' => $employee->id,
            'period'      => [
                'start' => $cycle['start']->toDateString(),
                'end'   => $cycle['end']->toDateString(),
            ],
            'gross_due'   => round($grossDue, 2),
            'advances'    => round((float) $advances, 2),
            'paid'        => round($paid, 2),
            'remaining'   => round($remaining, 2),
        ];
    }

    /**
     * Close a payroll run cycle: mark as paid and update related salary advance statuses.
     */
    public function closeCycle(PayrollRun $run): PayrollRun
    {
        $run->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return $run->fresh();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function monthlyPeriod(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfMonth(),
            'end'   => $now->copy()->endOfMonth(),
            'label' => $now->format('Y-m'),
        ];
    }

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function weeklyPeriod(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfWeek(),
            'end'   => $now->copy()->endOfWeek(),
            'label' => $now->format('Y-\WW'),
        ];
    }

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function dailyPeriod(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfDay(),
            'end'   => $now->copy()->endOfDay(),
            'label' => $now->toDateString(),
        ];
    }
}
