<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkPaymentJob;
use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

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

        // Prevent double-dispatch if already processing
        $progressKey = "bulk_pay:run:{$payrollRun->id}";
        try {
            $existing = Redis::connection('default')->get($progressKey);
            if ($existing) {
                $progress = json_decode($existing, true);
                if (in_array($progress['status'] ?? '', ['starting', 'processing'], true)) {
                    return response()->json([
                        'message'  => 'Bulk payment already in progress.',
                        'progress' => $progress,
                    ], 409);
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — allow dispatch to continue
        }

        ProcessBulkPaymentJob::dispatch($payrollRun->id, $actor->id);

        return response()->json([
            'message'        => 'Bulk payment processing started.',
            'payroll_run_id' => $payrollRun->id,
            'status'         => 'accepted',
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
        } catch (\Throwable $e) {
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status'         => 'unknown',
                'error'          => 'Redis unavailable: ' . $e->getMessage(),
            ], 503);
        }

        if ($raw === null) {
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status'         => 'not_started',
                'message'        => 'No bulk payment job found for this run.',
            ]);
        }

        $progress = json_decode($raw, true);

        return response()->json(array_merge([
            'payroll_run_id' => $payrollRun->id,
        ], $progress));
    }
}
