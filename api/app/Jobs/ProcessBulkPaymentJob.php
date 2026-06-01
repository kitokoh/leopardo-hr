<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\SalaryAdvance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Plan 65 — Traitement asynchrone des paiements en masse.
 *
 * Dispatched on the `payroll` queue after manager triggers bulk-pay.
 *
 * Steps:
 *   1. Mark all active SalaryAdvances in this payroll run as payment_declared.
 *   2. Dispatch payslip PDF and payment document jobs for each employee.
 *   3. Write progress to Redis for status polling.
 *   4. Create audit entry.
 */
class ProcessBulkPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $triggeredById,
    ) {
        $this->onQueue('payroll');
    }

    public function handle(): void
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        if ($run === null) {
            Log::warning("ProcessBulkPaymentJob: PayrollRun #{$this->payrollRunId} not found.");

            return;
        }

        $this->updateProgress(0, 'starting');

        // ── Step 1: Collect all pay slips for this run ──────────────────────
        /** @var Collection<int, PaySlip> $slips */
        $slips = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        $total = $slips->count();
        $done = 0;

        $this->updateProgress(0, 'processing', $total);

        foreach ($slips as $slip) {
            // Mark related salary advances as payment_declared
            SalaryAdvance::query()
                ->where('employee_id', $slip->employee_id)
                ->where('company_id', $run->company_id)
                ->where('validation_status', 'manager_approved')
                ->update([
                    'validation_status' => 'payment_declared',
                    'payment_declared_at' => now(),
                    'payment_declared_by' => $this->triggeredById,
                ]);

            // Dispatch legacy payslip PDF and Plan 62 document index generation.
            GeneratePaySlipPdfJob::dispatch($run->id, $slip->employee_id);
            GeneratePaymentDocumentJob::dispatchForPaySlip($slip, $this->triggeredById);

            $done++;
            $this->updateProgress($done, 'processing', $total);
        }

        // ── Step 2: Mark payroll run as paid ────────────────────────────────
        $run->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->updateProgress($total, 'completed', $total);

        Log::info("ProcessBulkPaymentJob: PayrollRun #{$run->id} bulk-paid — {$total} slips processed.");

        // ── Step 3: Audit log ───────────────────────────────────────────────
        Log::channel('stack')->info('bulk_payment_completed', [
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'triggered_by' => $this->triggeredById,
            'slips_count' => $total,
            'paid_at' => now()->toIso8601String(),
        ]);
    }

    private function updateProgress(int $done, string $status, int $total = 0): void
    {
        try {
            $key = "bulk_pay:run:{$this->payrollRunId}";
            $data = json_encode([
                'status' => $status,
                'done' => $done,
                'total' => $total,
                'updated_at' => now()->toIso8601String(),
            ]);
            Redis::connection('default')->setex($key, 3600, $data);
        } catch (Throwable $e) {
            // Non-critical: progress tracking failure should not stop the job
            Log::warning("ProcessBulkPaymentJob: Redis progress update failed: {$e->getMessage()}");
        }
    }
}
