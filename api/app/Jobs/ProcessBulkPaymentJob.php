<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
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
class ProcessBulkPaymentJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Durée de vie d'un claim Redis « bulk_pay:slip:* » (SET NX EX).
     *
     * Un claim plus vieux que $timeout est considéré orphelin (worker mort
     * entre claim et traitement, #6548) et peut être volé par la tentative
     * en cours : le slip est rejoué, jamais compté payé à tort.
     */
    public const CLAIM_TTL_SECONDS = 21600;

    private ?string $resolvedCompanyId = null;

    /**
     * @param  array<int, int>|null  $paySlipIds  Optional subset of pay_slips.id
     *                                            to pay in this batch (PA2-PAY-005 "selection multiple"). Null
     *                                            (the default) preserves the previous "pay every eligible slip
     *                                            in the run" behaviour.
     */
    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $triggeredById,
        public readonly ?array $paySlipIds = null,
    ) {
        $this->onQueue('payroll');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        return $this->resolvedCompanyId = $run?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
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

        // ── Step 1: Collect the pay slips to process for this run ─────────
        // PA2-PAY-005: when the manager selected a specific subset of pay
        // slips, only those (still eligible) slips are processed; any
        // requested id that doesn't belong to this run or isn't eligible is
        // silently ignored rather than failing the whole batch.
        /** @var Collection<int, PaySlip> $slips */
        $slips = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated'])
            ->when(
                $this->paySlipIds !== null,
                fn ($query) => $query->whereIn('id', $this->paySlipIds),
            )
            ->get();

        $total = $slips->count();
        $done = 0;

        // PA2-PAY-013: a single employee's failure (e.g. a stale advance
        // record, a corrupted contract) must never abort the whole batch —
        // every other slip must still be processed, and the failure must be
        // visible in the final results instead of being silently swallowed.
        $failures = [];

        $this->updateProgress(0, 'processing', $total);

        // QA #2997 — idempotence : chaque slip est CLAIMÉ en Redis (SET NX EX)
        // avant traitement. Un retry ($tries=3) ou un second job concurrent ne
        // re-traite jamais un slip déjà traité (les documents de paiement ne
        // sont pas générés 2×/3×) ; en cas d'échec le claim est libéré pour
        // permettre le retry de CE slip uniquement.
        $claimPrefix = "bulk_pay:slip:{$run->id}:";
        $redis = Redis::connection('default');
        $redisUnavailable = false;

        // #6548 : un claim non libéré (worker mort entre claim et traitement)
        // ne doit JAMAIS faire compter un slip non payé comme payé.
        $hasUnprocessedSlips = false;

        foreach ($slips as $slip) {
            try {
                $claimed = false;
                try {
                    $claimed = (bool) $redis->set($claimPrefix.$slip->id, '1', 'EX', self::CLAIM_TTL_SECONDS, 'NX'); // @phpstan-ignore argument.type, arguments.count
                } catch (Throwable $redisError) {
                    // #3857 : FAIL-CLOSED. Sans claim NX, un job concurrent
                    // (retry queue, second dispatch) re-traiterait des slips
                    // déjà payés → double déclaration de paiement. On ABORTE
                    // le lot entier : rien n'est marqué payé après ce point,
                    // le job échoue et la queue retry rejouera proprement une
                    // fois Redis revenu ($tries=3).
                    Log::error('ProcessBulkPaymentJob: Redis claim unavailable — batch aborted (fail-closed)', [
                        'payroll_run_id' => $run->id,
                        'pay_slip_id' => $slip->id,
                        'error' => $redisError->getMessage(),
                    ]);
                    $redisUnavailable = true;
                    break;
                }

                if (! $claimed) {
                    // #6548 : un claim non libéré peut être l'orphelin d'un
                    // worker mort ENTRE le claim et le traitement. Si le claim
                    // est plus vieux que le timeout du job, il est VOLÉ
                    // (del + re-NX) : le slip est rejoué — jamais compté payé
                    // à tort, jamais perdu non plus. Un claim récent (TTL
                    // complet) appartient à un worker concurrent → chemin
                    // fail-visible ci-dessous.
                    try {
                        $claimTtl = $redis->ttl($claimPrefix.$slip->id);
                        if (is_int($claimTtl) && $claimTtl >= 0 && $claimTtl < $this->timeout) {
                            $redis->del($claimPrefix.$slip->id);
                            $claimed = (bool) $redis->set($claimPrefix.$slip->id, '1', 'EX', self::CLAIM_TTL_SECONDS, 'NX'); // @phpstan-ignore argument.type, arguments.count
                        }
                    } catch (Throwable) {
                        // Redis indisponible → conservateur : suivre le
                        // chemin #6548 (échec visible) ci-dessous.
                    }
                }

                if (! $claimed) {
                    // #6548 : un claim existant peut être le vestige d'un
                    // worker mort ENTRE le claim et le traitement. Re-vérifier
                    // l'état réel du slip avant de le considérer traité :
                    // encore éligible (calculated/validated) sans document de
                    // paiement = JAMAIS payé → échec visible au lieu d'un
                    // skip silencieux qui ferait passer le run `paid` à tort.
                    $slip->refresh();
                    $hasPaymentDocument = PaymentDocument::query()
                        ->where('pay_slip_id', $slip->id)
                        ->where('document_type', PaymentDocument::TYPE_PAYMENT_SLIP)
                        ->exists();

                    if (! in_array($slip->status, ['calculated', 'validated'], true) || $hasPaymentDocument) {
                        // Slip réellement déjà traité → skip légitime.
                        $done++;

                        continue;
                    }

                    $failures[] = [
                        'pay_slip_id' => $slip->id,
                        'employee_id' => (int) $slip->employee_id,
                        'error' => 'slip_claim_unavailable_but_unprocessed',
                    ];
                    $hasUnprocessedSlips = true;
                    $done++;
                    $this->updateProgress($done, 'processing', $total, $failures);

                    continue;
                }

                $this->processSlip($run, $slip);
            } catch (Throwable $e) {
                Log::error('ProcessBulkPaymentJob: failed to process pay slip', [
                    'payroll_run_id' => $run->id,
                    'pay_slip_id' => $slip->id,
                    'employee_id' => $slip->employee_id,
                    'error' => $e->getMessage(),
                ]);

                // Libérer le claim : un retry pourra re-tenter CE slip.
                try {
                    $redis->del($claimPrefix.$slip->id);
                } catch (Throwable) {
                    // non bloquant
                }

                $failures[] = [
                    'pay_slip_id' => $slip->id,
                    'employee_id' => (int) $slip->employee_id,
                    'error' => $e->getMessage(),
                ];
            }

            $done++;
            $this->updateProgress($done, 'processing', $total, $failures);
        }

        if ($redisUnavailable) {
            // Fail-closed : libérer le claim du run pour permettre un
            // redispatch propre, puis échouer le job (retry $tries=3 avec
            // backoff par défaut de la queue).
            try {
                $redis->del("bulk_pay:run:{$run->id}");
            } catch (Throwable) {
                // non bloquant
            }

            throw new \RuntimeException(
                "ProcessBulkPaymentJob: batch aborted — Redis claim coordinator unavailable (payroll_run_id: {$run->id})."
            );
        }

        $succeeded = $total - count($failures);
        $finalStatus = $failures === [] ? 'completed' : 'completed_with_errors';

        // ── Step 2: Mark payroll run as paid, if fully settled ──────────────
        // The run is marked paid even with partial failures: the successful
        // slips were genuinely paid and must not be re-processed on retry;
        // failures are surfaced separately for manual follow-up.
        //
        // PA2-PAY-005: when the manager only selected a subset of pay slips,
        // other eligible slips in the run may still be awaiting payment —
        // the run must stay in its current status (not be marked 'paid')
        // until every calculated/validated slip has actually been paid.
        $remainingUnpaid = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated'])
            ->whereNotIn('id', $slips->pluck('id'))
            ->exists();

        if (! $remainingUnpaid && ! $hasUnprocessedSlips) {
            $run->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        $this->updateProgress($total, $finalStatus, $total, $failures);

        Log::info("ProcessBulkPaymentJob: PayrollRun #{$run->id} bulk-paid — {$succeeded}/{$total} slips processed successfully.");

        // ── Step 3: Persist an audit trail entry with the batch results ────
        AuditLog::query()->create([
            'company_id' => $run->company_id,
            'user_id' => $this->triggeredById,
            'action' => 'bulk_payment_processed',
            'auditable_type' => PayrollRun::class,
            'auditable_id' => $run->id,
            'new_values' => [
                'status' => $finalStatus,
                'total_slips' => $total,
                'succeeded' => $succeeded,
                'failed' => count($failures),
            ],
            'metadata' => [
                'payroll_run_id' => $run->id,
                'failures' => $failures,
                'paid_at' => now()->toIso8601String(),
            ],
        ]);

        // ── Step 4: Notify the manager who triggered the batch ──────────────
        // Partial results must reach a human even if nobody is polling the
        // status endpoint — this is the acceptance criterion for PA2-PAY-013.
        $this->notifyTrigger($run, $total, $succeeded, $failures);
    }

    /**
     * Processes a single pay slip: declares payment on its related salary
     * advances and dispatches PDF/document generation. Extracted as its
     * own method so a single slip's failure can be caught and reported
     * per-slip without interrupting the rest of the batch (PA2-PAY-013).
     */
    protected function processSlip(PayrollRun $run, PaySlip $slip): void
    {
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
        if ($slip->employee_id !== null) {
            GeneratePaySlipPdfJob::dispatch($run->id, $slip->employee_id);
        }
        GeneratePaymentDocumentJob::dispatchForPaySlip($slip, $this->triggeredById);
    }

    /**
     * @param  array<int, array{pay_slip_id: int, employee_id: int, error: string}>  $failures
     */
    private function notifyTrigger(PayrollRun $run, int $total, int $succeeded, array $failures): void
    {
        /** @var Employee|null $trigger */
        $trigger = Employee::query()->withoutGlobalScopes()->find($this->triggeredById);

        if ($trigger === null) {
            return;
        }

        $templateKey = $failures === [] ? 'bulk_payment_completed' : 'bulk_payment_completed_with_errors';

        try {
            app(CommunicationService::class)->notifyEmployee($trigger, $templateKey, [
                'payroll_run_id' => $run->id,
                'succeeded' => $succeeded,
                'failed' => count($failures),
                'total' => $total,
            ]);
        } catch (Throwable $e) {
            // Notification failure must never mask the fact that the batch
            // itself already completed (successfully or with errors).
            Log::warning('ProcessBulkPaymentJob: failed to notify trigger', [
                'payroll_run_id' => $run->id,
                'triggered_by' => $this->triggeredById,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array{pay_slip_id: int, employee_id: int, error: string}>  $failures
     */
    private function updateProgress(int $done, string $status, int $total = 0, array $failures = []): void
    {
        try {
            $key = "bulk_pay:run:{$this->payrollRunId}";
            $data = json_encode([
                'status' => $status,
                'done' => $done,
                'total' => $total,
                'failed' => count($failures),
                'failures' => $failures,
                'updated_at' => now()->toIso8601String(),
            ]);
            Redis::connection('default')->setex($key, 3600, $data);
        } catch (Throwable $e) {
            // Non-critical: progress tracking failure should not stop the job
            Log::warning("ProcessBulkPaymentJob: Redis progress update failed: {$e->getMessage()}");
        }
    }

    /**
     * #4205 : épuisement des retries — état visible + libération du claim du
     * run pour permettre un redispatch propre par le manager (le 503/abort
     * fail-closed #3857 reste la protection anti-doublon).
     */
    public function failed(Throwable $e): void
    {
        Log::error('ProcessBulkPaymentJob.failed', [
            'payroll_run_id' => $this->payrollRunId,
            'triggered_by' => $this->triggeredById,
            'exception' => $e->getMessage(),
        ]);

        try {
            $redis = Redis::connection('default');
            $redis->del("bulk_pay:run:{$this->payrollRunId}");

            // #6548 : libérer aussi les claims de slips posés par l'essai
            // avorté pour les slips encore éligibles (calculated/validated) —
            // un redispatch doit réellement les retraiter, pas les retrouver
            // « claimés » et les compter payés à tort. Les slips déjà traités
            // (documents générés) conservent leur claim : l'idempotence tient.
            $orphanSlipIds = PaySlip::query()
                ->withoutGlobalScopes()
                ->where('payroll_run_id', $this->payrollRunId)
                ->whereIn('status', ['calculated', 'validated'])
                ->pluck('id');

            foreach ($orphanSlipIds as $slipId) {
                if (! is_int($slipId) && ! is_string($slipId)) {
                    continue;
                }
                $slipClaimKey = 'bulk_pay:slip:'.$this->payrollRunId.':'.$slipId;
                $redis->del($slipClaimKey);
            }
        } catch (Throwable) {
            // non bloquant
        }
    }
}
