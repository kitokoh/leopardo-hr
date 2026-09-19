<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacyPurchasingService;
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
 * Cycle draft → ordered → partially_received → received | cancelled ;
 * transitions et réception déléguées à PharmacyPurchasingService (les
 * transitions invalides et la sur-réception sont refusées en 422). La
 * réception crée les lots + mouvements `receipt` (#7800). RBAC manager en
 * écriture (PharmacyPurchaseOrderPolicy), lecture employé du tenant.
 */
class PharmacyPurchaseOrderController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacyPurchasingService $purchasingService) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyPurchaseOrder::class);

        $query = PharmacyPurchaseOrder::query()->where('company_id', $actor->company_id);

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

        /** @var array{supplier_id: int, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: int, unit_price?: string|null}>} $payload */
        $payload = $request->validated();

        /** @var PharmacySupplier|null $supplier */
        $supplier = PharmacySupplier::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->find($payload['supplier_id']);

        if (! $supplier instanceof PharmacySupplier) {
            abort(404);
        }

        $order = $this->purchasingService->create(
            supplier: $supplier,
            lines: $payload['lines'],
            notes: $payload['notes'] ?? null,
            employeeId: (int) $actor->getAttribute('id'),
        );

        return response()->json(['data' => $this->payload($order, true)], 201);
    }

    public function show(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('view', $purchaseOrder);

        return response()->json(['data' => $this->payload($purchaseOrder, true)]);
    }

    /**
     * Transition draft → ordered (la commande part chez le fournisseur).
     */
    public function markOrdered(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        $order = $this->purchasingService->markOrdered($purchaseOrder);

        return response()->json(['data' => $this->payload($order)]);
    }

    /**
     * Annulation (draft|ordered uniquement — jamais une commande reçue).
     */
    public function cancel(Request $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        $order = $this->purchasingService->cancel($purchaseOrder);

        return response()->json(['data' => $this->payload($order)]);
    }

    /**
     * Réception (partielle ou totale) : lots + mouvements `receipt` créés
     * via PharmacyStockService, statut recalculé.
     */
    public function receive(ReceivePharmacyPurchaseOrderRequest $request, PharmacyPurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($purchaseOrder, $actor->company_id);
        $this->authorize('update', $purchaseOrder);

        /** @var array{receipts: list<array{line_id: int, quantity: int, batch_number: string, expiry_date: string, unit_cost?: string|null}>} $payload */
        $payload = $request->validated();

        $order = $this->purchasingService->receive(
            order: $purchaseOrder,
            receipts: $payload['receipts'],
            employeeId: (int) $actor->getAttribute('id'),
        );

        return response()->json(['data' => $this->payload($order, true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyPurchaseOrder $order, bool $withLines = false): array
    {
        $payload = [
            'id' => (int) $order->getAttribute('id'),
            'number' => $order->number,
            'supplier_id' => $order->supplier_id,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'received_at' => $order->received_at?->toIso8601String(),
            'notes' => $order->notes,
        ];

        if ($withLines) {
            $payload['lines'] = $order->lines()->orderBy('id')->get()
                ->map(fn (PharmacyPurchaseOrderLine $line): array => [
                    'id' => (int) $line->getAttribute('id'),
                    'product_id' => $line->product_id,
                    'quantity_ordered' => $line->quantity_ordered,
                    'quantity_received' => $line->quantity_received,
                    'unit_price' => $line->unit_price,
                ])
                ->all();
        }

        return $payload;
    }
}
