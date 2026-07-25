<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkPaymentJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            abort(403, 'Manager role required for bulk payment.');
        }
        if (! in_array($payrollRun->status, ['validated', 'calculated'], true)) {
            return response()->json([
                'message' => 'PayrollRun must be validated or calculated before bulk payment.',
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
        $progressKey = "bulk_pay:run:{$payrollRun->id}";
        try {
            $existing = Redis::connection('default')->get($progressKey);
            if ($existing) {
                $progress = json_decode($existing, true);
                if (in_array($progress['status'] ?? '', ['starting', 'processing'], true)) {
                    return response()->json([
                        'message' => 'Bulk payment already in progress.',
                        'progress' => $progress,
                    ], 409);
                }
            }
        } catch (Throwable) {
            // Redis unavailable — allow dispatch to continue
        }

        ProcessBulkPaymentJob::dispatch($payrollRun->id, $actor->id, $paySlipIds);

        return response()->json([
            'message' => 'Bulk payment processing started.',
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
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'unknown',
                'error' => 'Redis unavailable: '.$e->getMessage(),
            ], 503);
        }

        if ($raw === null) {
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'not_started',
                'message' => 'No bulk payment job found for this run.',
            ]);
        }

        $progress = json_decode($raw, true);

        return response()->json(array_merge([
            'payroll_run_id' => $payrollRun->id,
        ], $progress));
    }
}

