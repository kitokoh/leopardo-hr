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
use Illuminate\Redis\Connections\Connection;
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
     * #6548 — un claim plus vieux que le timeout du job (300 s) ne peut plus
     * appartenir à un worker vivant (un worker est tué à son propre timeout) :
     * c'est un orphelin avéré, volable par la tentative en cours. En dessous,
     * le claim peut être celui d'un worker concurrent encore actif → on ne
     * vole pas, on signale.
     */
    public const CLAIM_STALE_AFTER_SECONDS = 300;

    /** TTL du claim slip (6 h) — voir #6548 : les orphelins sont désormais
     * détectés par état réel + âge, le TTL n'est plus le seul garde-fou. */
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
                    // #6548 (audit fiabilité 2026-08-31) — un échec de claim
                    // ne signifie PLUS automatiquement « slip déjà traité » :
                    // le claim peut être ORPHELIN (worker mort entre le claim
                    // NX et la fin de processSlip — le catch qui libère le
                    // claim n'a jamais tourné). Avant ce correctif, le retry
                    // comptait le slip en `$done++` sans entrée dans
                    // `$failures` → run marqué `paid` sans que le salarié
                    // soit payé, zéro alerte, slip non rejoué avant le TTL
                    // (6 h). On re-vérifie donc l'ÉTAT RÉEL du slip (artefact
                    // de paiement + statut) avant de conclure.
                    $claimKey = $claimPrefix.$slip->id;
                    if ($this->slipWasActuallyProcessed($slip)) {
                        // Traité par une tentative précédente → succès réel.
                        $done++;

                        continue;
                    }

                    // Claim sans artefact : orphelin ou concurrent.
                    $claimAge = $this->claimAgeSeconds($redis, $claimKey);
                    if ($claimAge >= self::CLAIM_STALE_AFTER_SECONDS) {
                        // Claim plus vieux que le timeout du job (300 s) : le
                        // worker qui l'a posé est MORT (un worker vivant est
                        // tué à son propre timeout) → orphelin avéré. On vole
                        // le claim et on traite le slip DANS CETTE tentative.
                        try {
                            $redis->del($claimKey);
                        } catch (Throwable) {
                            // non bloquant
                        }

                        $this->processSlip($run, $slip);
                    } else {
                        // Claim récent : soit un worker actif le traite (double
                        // dispatch), soit un worker mort il y a < timeout — le
                        // redélivrage queue (retry_after ≥ timeout, #6535) le
                        // rendra orphelin au cycle suivant. On ne compte JAMAIS
                        // le slip réussi sans preuve : échec visible, jamais de
                        // « payé à tort » silencieux.
                        throw new \RuntimeException(
                            "ProcessBulkPaymentJob: slip #{$slip->id} claimé sans artefact de paiement (claim concurrent ou orphelin récent) — rejoué au prochain cycle."
                        );
                    }

                    $done++;

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

        if (! $remainingUnpaid) {
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
        GeneratePaySlipPdfJob::dispatch($run->id, (int) $slip->employee_id);
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
     * #6548 — un slip est réellement traité quand un artefact de paiement
     * (PaymentDocument) existe pour lui, ou quand il n'est plus éligible au
     * paiement (statut sorti de calculated/validated). C'est la « preuve »
     * qui distingue un claim légitime d'un claim orphelin.
     */
    private function slipWasActuallyProcessed(PaySlip $slip): bool
    {
        $artifact = PaymentDocument::query()
            ->where('pay_slip_id', $slip->id)
            ->where('company_id', $slip->company_id)
            ->exists();

        if ($artifact) {
            return true;
        }

        $fresh = PaySlip::query()
            ->where('id', $slip->id)
            ->where('company_id', $slip->company_id)
            ->first();

        return $fresh === null || ! in_array($fresh->status, ['calculated', 'validated'], true);
    }

    /**
     * Âge d'un claim en secondes (0 si la clé est absente ou sans TTL).
     */
    private function claimAgeSeconds(Connection $redis, string $claimKey): int
    {
        try {
            $ttl = $redis->ttl($claimKey);

            if (! is_int($ttl) || $ttl < 0) {
                // Clé absente (course : le worker vient de la libérer) ou sans
                // expiration : on considère le claim comme récent/non volable.
                return 0;
            }

            return max(0, self::CLAIM_TTL_SECONDS - $ttl);
        } catch (Throwable) {
            // Redis injoignable sur la lecture d'âge : on ne vole pas.
            return 0;
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
            Redis::connection('default')->del("bulk_pay:run:{$this->payrollRunId}");
        } catch (Throwable) {
            // non bloquant
        }
    }
}
