<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\User;
use App\Modules\Accounting\Domain\Models\AccountingPaymentOrder;
use App\Modules\Accounting\Infrastructure\Services\PaymentOrderService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\ExecutePaymentOrderRequest;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\PreparePaymentOrderRequest;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ordres de virement salarial — flux Paie → Comptabilité (issue #5239, Phase C).
 * RBAC : comptable (création/préparation/exécution), comptable + principal
 * (lecture) — porté par le middleware api.manager sur les routes.
 */
final class AccountingPaymentOrderController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $orders,
    ) {}

    /**
     * GET /api/v1/accounting/payment-orders?status=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['draft', 'prepared', 'executed'], true) ? $status : null;

        /** @var \Illuminate\Database\Eloquent\Collection<int, AccountingPaymentOrder> $orders */
        $orders = AccountingPaymentOrder::query()
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return response()->json([
            'data' => collect($orders->items())->map(
                static fn (AccountingPaymentOrder $order): array => [
                    'id' => $order->id,
                    'payroll_run_id' => $order->payroll_run_id,
                    'status' => $order->status,
                    'total_net' => $order->total_net,
                    'currency' => $order->currency,
                    'export_format' => $order->export_format,
                    'export_file' => $order->export_file,
                    'bank_reference' => $order->bank_reference,
                    'executed_at' => $order->executed_at?->toIso8601String(),
                ]
            )->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/accounting/payment-orders/{order}
     */
    public function show(AccountingPaymentOrder $order): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $order->id,
                'payroll_run_id' => $order->payroll_run_id,
                'status' => $order->status,
                'total_net' => $order->total_net,
                'currency' => $order->currency,
                'export_format' => $order->export_format,
                'export_file' => $order->export_file,
                'bank_reference' => $order->bank_reference,
                'executed_at' => $order->executed_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/accounting/payment-orders  {payroll_run_id}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payroll_run_id' => ['required', 'integer'],
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::query()
            ->where('company_id', (string) currentCompany()->id)
            ->findOrFail((int) $validated['payroll_run_id']);

        $order = $this->orders->createFromRun($run, $this->actorId($request));

        return response()->json(['data' => $this->payload($order)], 201);
    }

    /**
     * POST /api/v1/accounting/payment-orders/{order}/prepare  {format}
     */
    public function prepare(AccountingPaymentOrder $order, PreparePaymentOrderRequest $request): JsonResponse
    {
        $order = $this->orders->prepare(
            $order,
            (string) $request->validated('format'),
            $this->actorId($request),
        );

        return response()->json(['data' => $this->payload($order)]);
    }

    /**
     * POST /api/v1/accounting/payment-orders/{order}/execute  {bank_reference}
     */
    public function execute(AccountingPaymentOrder $order, ExecutePaymentOrderRequest $request): JsonResponse
    {
        $order = $this->orders->execute(
            $order,
            (string) $request->validated('bank_reference'),
            $request->filled('executed_at') ? Carbon::parse((string) $request->validated('executed_at')) : null,
            $this->actorId($request),
        );

        return response()->json(['data' => $this->payload($order)]);
    }

    private function actorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? (int) $user->id : null;
    }

    /** @return array<string, mixed> */
    private function payload(AccountingPaymentOrder $order): array
    {
        return [
            'id' => $order->id,
            'payroll_run_id' => $order->payroll_run_id,
            'status' => $order->status,
            'total_net' => $order->total_net,
            'currency' => $order->currency,
            'export_format' => $order->export_format,
            'export_file' => $order->export_file,
            'bank_reference' => $order->bank_reference,
            'executed_at' => $order->executed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
