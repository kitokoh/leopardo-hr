<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkPaymentJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\BulkPaymentCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plan 65 — Paiement en masse.
 *
 * POST /payroll-runs/{id}/bulk-pay         — dispatch le job + 202 Accepted
 * GET  /payroll-runs/{id}/bulk-pay/status  — statut d'avancement
 *
 * #9874 : le coordinateur (verrou anti-double-dispatch + avancement) vit dans
 * PostgreSQL (`bulk_payment_claims`), plus dans Redis. Redis n'est plus un
 * point unique de défaillance sur un mouvement d'argent — il était le seul
 * composant dont la panne arrêtait la paie de tous les tenants.
 */
class BulkPaymentController extends Controller
{
    public function __construct(
        private readonly BulkPaymentCoordinator $coordinator,
    ) {}

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
        /** @var array{pay_slip_ids?: array<int, int>|null} $validated */
        $validated = $request->validate([
            'pay_slip_ids' => ['nullable', 'array', 'min:1'],
            'pay_slip_ids.*' => ['integer', 'distinct'],
        ]);
        $paySlipIds = $validated['pay_slip_ids'] ?? null;

        // Prevent double-dispatch if already processing.
        //
        // #9874 : l'exclusion mutuelle vient d'une contrainte
        // UNIQUE (payroll_run_id) en base, pas d'un `SET NX` Redis. Un seul
        // INSERT concurrent peut réussir → un seul dispatch. Redis n'est plus
        // dans le chemin critique : sa panne n'empêche plus de payer.
        try {
            $claim = $this->coordinator->claim($payrollRun->id, $payrollRun->company_id, $actor->id);
        } catch (Throwable $e) {
            // FAIL-CLOSED conservé : si la base est injoignable, on refuse le
            // dispatch plutôt que de risquer deux jobs qui paieraient 2× les
            // mêmes bulletins. Mais c'est PostgreSQL — déjà indispensable au
            // reste de la requête — et non plus un cache optionnel.
            Log::error('payroll.bulk_payment.coordinator_unavailable', [
                'payroll_run_id' => $payrollRun->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('payroll.bulk_temporarily_unavailable'),
                'error' => 'BULK_PAYMENT_COORDINATOR_UNAVAILABLE',
            ], 503);
        }

        if (! $claim['acquired']) {
            return response()->json([
                'message' => __('payroll.bulk_already_in_progress'),
                'progress' => $claim['progress'],
            ], 409);
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
     * Returns the current progress of a bulk payment job from PostgreSQL
     * (#9874 — plus de perte d'état si Redis redémarre).
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

        // #9874 : l'état vit en base. Une panne Redis ne rend plus le statut
        // d'un paiement en cours indisponible (503) ni ne le perd.
        try {
            $progress = $this->coordinator->status($payrollRun->id);
        } catch (Throwable $e) {
            Log::error('payroll.bulk_payment.status_failed', [
                'payroll_run_id' => $payrollRun->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'unknown',
                'error' => 'BULK_PAYMENT_STATUS_UNAVAILABLE',
            ], 503);
        }

        if ($progress === null) {
            return response()->json([
                'payroll_run_id' => $payrollRun->id,
                'status' => 'not_started',
                'message' => __('payroll.bulk_no_job_found'),
            ]);
        }

        return response()->json(array_merge([
            'payroll_run_id' => $payrollRun->id,
        ], $progress));
    }
}
