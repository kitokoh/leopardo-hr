<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkPaymentJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Plan 65 — Paiement en masse.
 *
 * POST /payroll-runs/{id}/bulk-pay         — dispatch le job + 202 Accepted
 * GET  /payroll-runs/{id}/bulk-pay/status  — statut via Redis (avancement)
 */
class BulkPaymentController extends Controller
{
    /**
     * POST /payroll-runs/{payrollRun}/bulk-pay
     *
     * Triggers async bulk payment processing for a validated payroll run.
     * Returns 202 Accepted immediately; actual work is done in ProcessBulkPaymentJob.
     */
    public function bulkPay(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }
        if (! in_array($payrollRun->status, ['validated', 'locked'], true)) {
            return response()->json([
                'message' => __('payroll.bulk_run_must_be_validated'),
            ], 422);
        }

        // PA2-PAY-005: a manager can pick a subset of employees/pay slips to
        // pay in this batch (e.g. "pay everyone except the two under
        // dispute") instead of always paying the whole run. Omitting
        // pay_slip_ids keeps the previous "pay everyone in the run"
        // behaviour so existing integrations are unaffected.
        $validated = $request->validate([
            'pay_slip_ids' => ['nullable', 'array', 'min:1'],
            'pay_slip_ids.*' => ['integer', 'distinct'],
        ]);
        $paySlipIds = $validated['pay_slip_ids'] ?? null;

        // Prevent double-dispatch if already processing
        // QA #2997 : garde ATOMIQUE (SET NX EX) — avant, un `get` puis
        // `dispatch` (TOCTOU) laissait passer deux dispatches simultanés.
        $progressKey = "bulk_pay:run:{$payrollRun->id}";
        $claimPayload = json_encode([
            'status' => 'starting',
            'started_at' => now()->toIso8601String(),
            'triggered_by' => $actor->id,
        ]);
        try {
            $acquired = Redis::connection('default')
                ->set($progressKey, $claimPayload, 'EX', 21600, 'NX'); // @phpstan-ignore argument.type, arguments.count

            if (! $acquired) {
                $existing = Redis::connection('default')->get($progressKey);
                $progress = $existing ? json_decode($existing, true) : [];
                if (in_array($progress['status'] ?? '', ['starting', 'processing'], true)) {
                    return response()->json([
                        'message' => __('payroll.bulk_already_in_progress'),
                        'progress' => $progress,
                    ], 409);
                }
                // Statut stale (completed*/crash) : on reprend la main.
                Redis::connection('default')->set($progressKey, $claimPayload, 'EX', 21600); // @phpstan-ignore argument.type, arguments.count
            }
        } catch (Throwable $e) {
            // #3857 : FAIL-CLOSED. Redis est le coordinateur anti-doublon
            // (claim NX du run). S'il est indisponible, un dispatch sans
            // claim laisserait deux requêtes concurrentes (retry client,
            // double-clic) lancer deux jobs qui paieraient 2× les mêmes
            // bulletins — mouvement d'argent, inacceptable. On refuse le
            // dispatch avec un 503 explicite : le client retry-aware pourra
            // re-tenter, aucun job n'est lancé.
            Log::error('payroll.bulk_payment.redis_unavailable', [
                'payroll_run_id' => $payrollRun->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('payroll.bulk_temporarily_unavailable'),
                'error' => 'BULK_PAYMENT_COORDINATOR_UNAVAILABLE',
            ], 503);
        }

        ProcessBulkPaymentJob::dispatch($payrollRun->id, $actor->id, $paySlipIds);

        return response()->json([
            'message' => __('payroll.bulk_started'),
            'payroll_run_id' => $payrollRun->id,
            'selected_pay_slip_count' => $paySlipIds !== null ? count($paySlipIds) : null,
            'status' => 'accepted',
        ], 202);
    }

    /**
     * GET /payroll-runs/{payrollRun}/bulk-pay/status
     *
     * Returns the current progress of a bulk payment job from Redis.
     */
    public function bulkPayStatus(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $progressKey = "bulk_pay:run:{$payrollRun->id}";

        try {
            $raw = Redis::connection('default')->get($progressKey);
        } catch (Throwable $e) {
            Log::error('payroll.bulk_payment.status_redis_failed', [
                'payroll_run_id' => $payrollRun->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'unknown',
                'error' => 'BULK_PAYMENT_STATUS_UNAVAILABLE',
            ], 503);
        }

        if ($raw === null) {
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'not_started',
                'message' => __('payroll.bulk_no_job_found'),
            ]);
        }

        $progress = json_decode($raw, true);

        return response()->json(array_merge([
            'payroll_run_id' => $payrollRun->id,
        ], $progress));
    }
}
