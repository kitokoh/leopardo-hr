<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProcessPayrollBatchJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * PA2-PAY-012 — Nightly progressive payroll pre-calculation.
 *
 * Recalculating every draft payroll run only on the exact payment day (the
 * only trigger that existed before this command — a manager clicking
 * "Calculate" manually via `POST /payroll-runs/{id}/calculate`) means the
 * first real signal that a run is broken (missing salary structure, bad
 * country rules, a stuck queue worker) surfaces the same day employees are
 * expecting to be paid, with no time left to fix it.
 *
 * This command runs nightly (see `withSchedule()` in `bootstrap/app.php`)
 * and progressively (re)calculates every `draft`/`calculated` payroll run
 * whose period is approaching its configured pay day (`PayrollCycleService`
 * pay-cycle settings, PA2-PAY-011), each time it runs, until the run is
 * validated by a manager. Every calculation still happens through the
 * existing `ProcessPayrollBatchJob` (already `ShouldQueue`, `$tries = 3`,
 * tenant-scoped via `EnsureTenantContext`) — this command only decides
 * *which* runs are due and dispatches jobs for them; it re-uses the job's
 * own retry policy instead of re-implementing one, and every decision
 * (dispatched / skipped / not-yet-due) is logged on the `structured`
 * channel so a stuck precalculation can be diagnosed from logs alone.
 *
 * A run recalculated every night simply gets fresher figures each time
 * (new employees, updated salary structures, corrected attendance) right
 * up until a manager validates it — recalculating a `calculated` run is
 * already idempotent and side-effect-free (`PayrollCalculator::calculateRun()`
 * deletes and regenerates that run's own pay slips inside a transaction).
 */
class PrecalculatePayrollRuns extends Command
{
    protected $signature = 'payroll:precalculate
                                {--days=5 : Start precalculating this many days before the next payment date}
                                {--dry-run : Preview without dispatching any job}';

    protected $description = 'Progressively pre-calculate draft/calculated payroll runs approaching their pay day, with per-run retries and structured logs';

    public function handle(PayrollCycleService $cycleService): int
    {
        $windowDays = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Payroll precalculation: scanning runs due within {$windowDays} day(s) of their pay day.");

        $runs = PayrollRun::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['draft', 'calculated'])
            ->get();

        $dispatched = 0;
        $skipped = 0;
        $notDue = 0;

        foreach ($runs as $run) {
            $company = $this->resolveCompany($run);

            if ($company === null) {
                $skipped++;
                Log::channel('structured')->warning('payroll.precalculate.skip_missing_company', [
                    'payroll_run_id' => $run->id,
                    'company_id' => $run->company_id,
                ]);

                continue;
            }

            if (in_array($company->status, ['suspended', 'expired'], true)) {
                $skipped++;
                Log::channel('structured')->info('payroll.precalculate.skip_inactive_company', [
                    'payroll_run_id' => $run->id,
                    'company_id' => $company->id,
                    'company_status' => $company->status,
                ]);

                continue;
            }

            $nextPaymentDate = $this->resolveNextPaymentDate($cycleService, $company, $run);
            $daysUntilPayment = Carbon::now($company->timezone ?: 'UTC')
                ->startOfDay()
                ->diffInDays($nextPaymentDate, false);

            if ($daysUntilPayment > $windowDays) {
                $notDue++;
                Log::channel('structured')->debug('payroll.precalculate.not_due', [
                    'payroll_run_id' => $run->id,
                    'company_id' => $company->id,
                    'next_payment_date' => $nextPaymentDate->toDateString(),
                    'days_until_payment' => $daysUntilPayment,
                ]);

                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Would dispatch payroll_run={$run->id} company={$company->id} days_until_payment={$daysUntilPayment}");

                continue;
            }

            ProcessPayrollBatchJob::dispatch($run->id, (string) $company->id);
            $dispatched++;

            Log::channel('structured')->info('payroll.precalculate.dispatched', [
                'payroll_run_id' => $run->id,
                'company_id' => $company->id,
                'next_payment_date' => $nextPaymentDate->toDateString(),
                'days_until_payment' => $daysUntilPayment,
                'run_status' => $run->status,
            ]);
        }

        $this->info("Precalculation complete: dispatched={$dispatched} skipped={$skipped} not_due={$notDue} total_scanned={$runs->count()}.");

        Log::channel('structured')->info('payroll.precalculate.run_complete', [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'not_due' => $notDue,
            'total_scanned' => $runs->count(),
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    private function resolveCompany(PayrollRun $run): ?Company
    {
        if ($run->company_id === null) {
            return null;
        }

        return Company::query()->withoutGlobalScopes()->find($run->company_id);
    }

    /**
     * Reuses `PayrollCycleService::getCurrentCycle()`'s pay-day math
     * (PA2-PAY-011 settings: daily/weekly/monthly, configured pay day, week
     * start) but anchored on the payroll run's own `period_end` rather than
     * "today" — the current cycle helper is meant for live employee-balance
     * reads (today's pay period), while a precalculation nightly job needs
     * the payment date for *this specific run*'s period, which may be the
     * cycle before or after the current one at any given point in the month.
     */
    private function resolveNextPaymentDate(PayrollCycleService $cycleService, Company $company, PayrollRun $run): Carbon
    {
        $settings = $cycleService->getPayCycleSettings($company);

        if ($settings['pay_cycle'] !== 'monthly') {
            return Carbon::instance($run->period_end)->copy()->startOfDay();
        }

        $periodEnd = Carbon::instance($run->period_end);
        $payDay = min($settings['pay_day'], $periodEnd->daysInMonth);

        return $periodEnd->copy()->startOfMonth()->addDays($payDay - 1)->startOfDay();
    }
}
