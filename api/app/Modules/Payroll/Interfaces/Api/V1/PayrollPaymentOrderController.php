<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollPaymentOrderResource;
use App\Modules\Payroll\Domain\Models\PayrollPaymentOrder;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollPaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5239 — Phase C : ordre de virement.
 *
 * Préparation (comptable) d'un ordre de virement depuis le net par employé
 * d'un run validé, exécution (référence banque + date) et rapprochement.
 * Lecture pour principal/comptable ; RH exclu (il ne touche qu'au run).
 */
class PayrollPaymentOrderController extends Controller
{
    public function __construct(
        private readonly PayrollPaymentOrderService $orders,
    ) {}

    /**
     * POST /api/v1/payroll-runs/{run}/payment-order — prépare un ordre de
     * virement pour un run validé (comptable).
     */
    public function prepare(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        // #5239 : la préparation de l'ordre de virement est réservée au comptable.
        if ($actor->hasManagerRole('comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $order = $this->orders->prepare(
                $payrollRun,
                $validated['format'] ?? PayrollPaymentOrderService::DEFAULT_FORMAT,
                actor: $actor,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'PAYMENT_ORDER_PREPARATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new PayrollPaymentOrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/payment-orders — ordres de virement du tenant
     * (principal/comptable).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = PayrollPaymentOrder::query()
            ->where('company_id', $actor->company_id)
            ->with('payrollRun:id,period_start,period_end,status')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return PayrollPaymentOrderResource::collection($orders)->response();
    }

    /**
     * GET /api/v1/payment-orders/{order} — détail d'un ordre
     * (principal/comptable).
     */
    public function show(Request $request, PayrollPaymentOrder $paymentOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paymentOrder->company_id !== $actor->company_id) {
            abort(404);
        }

        return (new PayrollPaymentOrderResource($paymentOrder->load('items')))->response();
    }

    /**
     * POST /api/v1/payment-orders/{order}/execute — exécute l'ordre
     * (comptable, référence banque + date).
     */
    public function execute(Request $request, PayrollPaymentOrder $paymentOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paymentOrder->company_id !== $actor->company_id) {
            abort(404);
        }
        // #5239 : l'exécution de l'ordre de virement est réservée au comptable.
        if ($actor->hasManagerRole('comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        $validated = $request->validate([
            'bank_reference' => ['required', 'string', 'max:128'],
            'executed_at' => ['nullable', 'date'],
        ]);

        try {
            $order = $this->orders->markExecuted(
                $paymentOrder,
                $validated['bank_reference'],
                $validated['executed_at'] ?? null,
                $actor,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'PAYMENT_ORDER_EXECUTION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new PayrollPaymentOrderResource($order))->response();
    }

    /**
     * POST /api/v1/payment-orders/{order}/reconcile — rapprochement
     * (comptable).
     */
    public function reconcile(Request $request, PayrollPaymentOrder $paymentOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paymentOrder->company_id !== $actor->company_id) {
            abort(404);
        }
        // #5239 : le rapprochement est réservé au comptable.
        if ($actor->hasManagerRole('comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        try {
            $order = $this->orders->reconcile($paymentOrder, $actor);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'PAYMENT_ORDER_RECONCILIATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new PayrollPaymentOrderResource($order))->response();
    }
}
