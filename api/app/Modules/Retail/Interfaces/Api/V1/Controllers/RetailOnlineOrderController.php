<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailOnlineOrderService;
use App\Modules\Retail\Application\Services\RetailPaymentService;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Interfaces\Api\V1\Requests\CancelRetailOrderRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\ConfirmRetailOnlineOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Commandes en ligne Leopardo Marche — espace vendeur
 * (BC-17 RETAIL, #7808).
 *
 * deny-by-default (RetailOrderPolicy) : lecture membres du tenant,
 * pilotage logistique (confirm/ready/ship/deliver/cancel) reserve
 * principal/rh (ability `fulfill`). Toute la logique metier (machine
 * d'etats, decrement/restauration de stock via RetailStockService) est
 * portee par RetailOnlineOrderService ; transition invalide → 422
 * INVALID_TRANSITION. Isolation : toute ressource d'un autre tenant repond
 * 404 (lecon fail-closed #3727). Seules les commandes `source = online`
 * sont servies ici.
 */
class RetailOnlineOrderController extends Controller
{
    public function __construct(
        private readonly RetailOnlineOrderService $orders,
        private readonly RetailPaymentService $payments,
    ) {}

    /**
     * GET /retail/online/orders — liste (filtre fulfillment_status, tri
     * recent, uniquement source=online).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailOrder::class);

        $statuses = array_map(
            static fn (RetailFulfillmentStatus $s): string => $s->value,
            RetailFulfillmentStatus::cases()
        );

        $request->validate([
            'fulfillment_status' => ['nullable', Rule::in($statuses)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = RetailOrder::query()
            ->where('company_id', $actor->company_id)
            ->where('source', 'online');

        if ($request->filled('fulfillment_status')) {
            $query->where('fulfillment_status', $request->input('fulfillment_status'));
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
     * GET /retail/online/orders/{order} — detail (commande + lignes).
     */
    public function show(Request $request, RetailOrder $order): JsonResponse
    {
        $this->assertOnlineOrderOfTenant($request, $order);
        $this->authorize('view', $order);

        return response()->json(['data' => $this->detailPayload($order)]);
    }

    /**
     * POST /retail/online/orders/{order}/confirm — `pending → confirmed` :
     * decremente le stock (mouvements `sale` sur l'emplacement fourni ou
     * celui de la commande) et passe le statut historique a `completed`.
     */
    public function confirm(ConfirmRetailOnlineOrderRequest $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->assertOnlineOrderOfTenant($request, $order);
        $this->authorize('fulfill', $order);

        $location = null;

        if ($request->filled('location_id')) {
            /** @var RetailLocation $location */
            $location = RetailLocation::query()
                ->where('company_id', $actor->company_id)
                ->findOrFail($request->integer('location_id'));
        }

        $updated = $this->orders->confirm($order, $location, (int) $actor->id);

        return response()->json(['data' => $this->detailPayload($updated)]);
    }

    /**
     * POST /retail/online/orders/{order}/ready — `confirmed → ready`.
     */
    public function ready(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Ready);
    }

    /**
     * POST /retail/online/orders/{order}/ship — `→ shipped` (shipped_at).
     */
    public function ship(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Shipped);
    }

    /**
     * POST /retail/online/orders/{order}/deliver — `shipped → delivered`
     * (delivered_at, terminal).
     */
    public function deliver(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Delivered);
    }

    /**
     * POST /retail/online/orders/{order}/cancel — annulation (mouvements
     * `return` restaurant le stock si la commande etait confirmee).
     */
    public function cancel(CancelRetailOrderRequest $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->assertOnlineOrderOfTenant($request, $order);
        $this->authorize('cancel', $order);

        $cancelled = $this->orders->cancel(
            order: $order,
            note: $request->filled('note') ? (string) $request->input('note') : null,
            userId: (int) $actor->id,
        );

        return response()->json(['data' => $this->detailPayload($cancelled)]);
    }

    /**
     * POST /retail/online/orders/{order}/refund — remboursement du
     * paiement en ligne (#7812 E). Reserve principal/rh (ability `pay`,
     * meme portee que l'encaissement POS), UNIQUEMENT si la commande porte
     * un intent `succeeded` — sinon 422 PAYMENT_NOT_REFUNDABLE. Appelle
     * `provider->refund` puis bascule intent + commande + trace
     * RetailOrderPayment en `refunded` (auditable).
     */
    public function refund(Request $request, RetailOrder $order): JsonResponse
    {
        $this->assertOnlineOrderOfTenant($request, $order);
        $this->authorize('pay', $order);

        $intent = $this->payments->refund($order);

        $order->refresh();

        return response()->json(['data' => [
            ...$this->detailPayload($order),
            'payment' => [
                'intent_reference' => $intent->intent_reference,
                'provider' => $intent->provider,
                'status' => $intent->status->value,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency,
            ],
        ]]);
    }

    /**
     * Progression logistique commune (ready/ship/deliver).
     */
    private function transition(Request $request, RetailOrder $order, RetailFulfillmentStatus $target): JsonResponse
    {
        $this->assertOnlineOrderOfTenant($request, $order);
        $this->authorize('fulfill', $order);

        $updated = $this->orders->progress($order, $target);

        return response()->json(['data' => $this->detailPayload($updated)]);
    }

    /**
     * Isolation tenant + canal : 404 pour une commande d'un autre tenant
     * ou hors canal `online` (fail-closed #3727).
     */
    private function assertOnlineOrderOfTenant(Request $request, RetailOrder $order): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id
            || $order->source->value !== 'online') {
            abort(404);
        }
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
            'reference' => $order->reference,
            'status' => $order->status->value,
            'fulfillment_status' => $order->fulfillment_status?->value,
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'source' => $order->source->value,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'delivery_address' => $order->delivery_address,
            'delivery_city' => $order->delivery_city,
            'delivery_notes' => $order->delivery_notes,
            'note' => $order->note,
            'payment_method' => $order->payment_method?->value,
            'payment_status' => $order->payment_status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'version' => $order->version,
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Payload detail : commande + lignes.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(RetailOrder $order): array
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

        return [
            ...$this->orderPayload($order),
            'items' => $items,
        ];
    }
}
