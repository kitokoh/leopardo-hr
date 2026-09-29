<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Actions\CancelPharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\PlacePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\ReceivePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrderLine;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\ReceivePharmacyPurchaseOrderRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyPurchaseOrderRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes d'achat d'officine — PHARMA-004 (#7801).
 *
 * Cycle draft → ordered → partially_received → received | cancelled,
 * réception → lots + mouvements `receipt` (PharmacyStockService), numéro
 * `PO-YYYY-XXXX` séquencé par tenant, sur-réception refusée.
 */
class PharmacyPurchaseOrderController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(
        private readonly CreatePharmacyPurchaseOrderAction $createOrder,
        private readonly PlacePharmacyPurchaseOrderAction $placeOrder,
        private readonly CancelPharmacyPurchaseOrderAction $cancelOrder,
        private readonly ReceivePharmacyPurchaseOrderAction $receiveOrder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyPurchaseOrder::class);

        $query = PharmacyPurchaseOrder::query()
            ->with('lines')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        $orders = $query->orderByDesc('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($orders->items())->map(fn (PharmacyPurchaseOrder $order): array => $this->payload($order)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(StorePharmacyPurchaseOrderRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacyPurchaseOrder::class);

        // Le fournisseur doit appartenir au tenant (404 sinon — pas de fuite).
        $supplier = PharmacySupplier::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($request->integer('supplier_id'))
            ->first();

        if ($supplier === null) {
            abort(404);
        }

        /** @var array{supplier_id: int, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: int, unit_price?: string|null}>} $payload */
        $payload = $request->validated();

        $order = $this->createOrder->execute($actor, $supplier, $payload);

        return response()->json(['data' => $this->payload($order->load('lines'))], 201);
    }

    public function show(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('view', $purchaseOrder);

        return response()->json(['data' => $this->payload($purchaseOrder->load('lines'))]);
    }

    public function place(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        $order = $this->placeOrder->execute($purchaseOrder);

        return response()->json(['data' => $this->payload($order->load('lines'))]);
    }

    public function cancel(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        $order = $this->cancelOrder->execute($purchaseOrder);

        return response()->json(['data' => $this->payload($order->load('lines'))]);
    }

    public function receive(ReceivePharmacyPurchaseOrderRequest $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        /** @var array{lines: list<array{line_id: int, quantity: int, batch_number: string, expiry_date: string, unit_cost?: string|null}>} $payload */
        $payload = $request->validated();

        $order = $this->receiveOrder->execute($purchaseOrder, $payload, $actor);

        return response()->json(['data' => $this->payload($order->load('lines'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyPurchaseOrder $order): array
    {
        return [
            'id' => (int) $order->getAttribute('id'),
            'number' => $order->number,
            'supplier_id' => $order->supplier_id,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'received_at' => $order->received_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'notes' => $order->notes,
            'lines' => $order->lines->map(fn (PharmacyPurchaseOrderLine $line): array => [
                'id' => (int) $line->getAttribute('id'),
                'product_id' => $line->product_id,
                'quantity_ordered' => $line->quantity_ordered,
                'quantity_received' => $line->quantity_received,
                'unit_price' => (string) $line->unit_price,
            ])->all(),
        ];
    }
}
