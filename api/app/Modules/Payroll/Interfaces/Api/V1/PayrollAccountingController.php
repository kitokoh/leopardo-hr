<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollAccountingEntryResource;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollAccountingEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5239 — Phase C : écritures salariales automatiques.
 *
 * Lecture (principal/comptable) et régénération (comptable) des écritures
 * comptables d'un run de paie validé. Les écritures sont générées
 * automatiquement à la validation (observer AuditLog) ; cette API permet la
 * consultation et une régénération manuelle idempotente.
 */
class PayrollAccountingController extends Controller
{
    public function __construct(
        private readonly PayrollAccountingEntryService $entries,
    ) {}

    /**
     * GET /api/v1/payroll-runs/{run}/accounting-entries
     * — écritures du run (traçabilité run → comptabilité).
     */
    public function index(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }

        return PayrollAccountingEntryResource::collection($this->entries->entriesForRun($payrollRun))->response();
    }

    /**
     * POST /api/v1/payroll-runs/{run}/accounting-entries/regenerate
     * — régénération idempotente (comptable).
     */
    public function regenerate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        // #5239 : la régénération des écritures est réservée au comptable.
        if ($actor->hasManagerRole('comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        try {
            $count = $this->entries->generateForRun($payrollRun, $actor);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'PAYROLL_ENTRIES_GENERATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'payroll_run_id' => $payrollRun->id,
            'generated_lines' => $count,
            'balance' => $this->entries->balanceForRun($payrollRun),
        ]);
    }
}
