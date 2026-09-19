<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailPosService;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailPosSession;
use App\Modules\Retail\Interfaces\Api\V1\Requests\CancelRetailOrderRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailOrderPaymentRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes de vente POS du module Retail (BC-17 RETAIL, #7674).
 *
 * deny-by-default (RetailOrderPolicy) : lecture membres du tenant,
 * creation/encaissement/annulation reserves principal/rh. Totaux et prix
 * TOUJOURS calcules serveur ; toute la logique metier (idempotence,
 * completion, decrement de stock, survente tracee) est portee par
 * RetailPosService. Isolation : toute ressource d'un autre tenant repond
 * 404 (lecon fail-closed #3727).
 */
class RetailOrderController extends Controller
{
    public function __construct(private readonly RetailPosService $posService) {}

    /**
     * Liste des commandes (filtres status / pos_session_id / source, desc).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailOrder::class);

        $query = RetailOrder::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('pos_session_id')) {
            $query->where('pos_session_id', $request->integer('pos_session_id'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        $orders = $query
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($orders->items())
                ->map(fn (RetailOrder $o): array => $this->orderPayload($o)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Cree une commande `draft` sur une session ouverte (snapshots prix
     * serveur, idempotency_key optionnelle → rejeu sans doublon).
     */
    public function store(StoreRetailOrderRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailOrder::class);

        /** @var RetailPosSession $session */
        $session = RetailPosSession::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($request->integer('pos_session_id'));

        /** @var list<array{product_id: int, quantity: float}> $lines */
        $lines = array_map(
            static fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (float) $line['quantity'],
            ],
            (array) $request->input('lines'),
        );

        $order = $this->posService->createOrder(
            session: $session,
            lines: $lines,
            note: $request->filled('note') ? (string) $request->input('note') : null,
            idempotencyKey: $request->filled('idempotency_key') ? (string) $request->input('idempotency_key') : null,
        );

        return response()->json(['data' => $this->receiptPayload($order)], 201);
    }

    /**
     * Ticket de caisse : commande + lignes + paiements.
     */
    public function show(Request $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $order);

        return response()->json(['data' => $this->receiptPayload($order)]);
    }

    /**
     * Encaisse un paiement ; quand les paiements captures couvrent le total,
     * la commande passe `completed` et le stock est decremente (mouvements
     * `sale` traces — survente : cf. RetailPosService).
     */
    public function addPayment(StoreRetailOrderPaymentRequest $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('pay', $order);

        $updated = $this->posService->addPayment(
            order: $order,
            method: RetailPaymentMethod::from((string) $request->input('method')),
            amountMinor: $request->integer('amount_minor'),
            idempotencyKey: $request->filled('idempotency_key') ? (string) $request->input('idempotency_key') : null,
            reference: $request->filled('reference') ? (string) $request->input('reference') : null,
            userId: (int) $actor->id,
        );

        return response()->json(['data' => $this->receiptPayload($updated)], 201);
    }

    /**
     * Annule la commande (`completed` → mouvements `return` restaurant le
     * stock, puis `cancelled`).
     */
    public function cancel(CancelRetailOrderRequest $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('cancel', $order);

        $cancelled = $this->posService->cancelOrder(
            order: $order,
            note: $request->filled('note') ? (string) $request->input('note') : null,
            userId: (int) $actor->id,
        );

        return response()->json(['data' => $this->receiptPayload($cancelled)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(RetailOrder $order): array
    {
        return [
            'id' => $order->id,
            'company_id' => $order->company_id,
            'location_id' => $order->location_id,
            'pos_session_id' => $order->pos_session_id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'source' => $order->source->value,
            'note' => $order->note,
            'version' => $order->version,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Payload « ticket » : commande + lignes + paiements.
     *
     * @return array<string, mixed>
     */
    private function receiptPayload(RetailOrder $order): array
    {
        $items = $order->items()->orderBy('line_index')->get()
            ->map(static fn (RetailOrderItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'line_total_minor' => $item->line_total_minor,
                'line_index' => $item->line_index,
            ])->all();

        $payments = $order->payments()->orderBy('id')->get()
            ->map(static fn (RetailOrderPayment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'reference' => $payment->reference,
            ])->all();

        return [
            ...$this->orderPayload($order),
            'items' => $items,
            'payments' => $payments,
        ];
    }
}
