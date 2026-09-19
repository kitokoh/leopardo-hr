<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailOnlineOrderService;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Exceptions\InvalidRetailFulfillmentTransition;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Interfaces\Api\V1\Requests\UpdateRetailOnlineSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace VENDEUR de la boutique en ligne Retail (BC-17, #7808).
 *
 * Surface authentifiée du tenant (`/retail/online/*`, pile tenant +
 * `module.retail`) : réglages de la boutique (opt-in marketplace),
 * commandes web (liste/détail) et transitions de la machine d'états
 * pending → confirmed → ready → shipped → delivered (+ cancelled).
 *
 * deny-by-default : réglages réservés principal/rh
 * (RetailOnlineSettingsPolicy), transitions réservées principal/rh
 * (RetailOrderPolicy::fulfill) ; lecture membres du tenant. Isolation :
 * toute ressource d'un autre tenant répond 404 (leçon fail-closed #3727).
 * Transition hors du graphe → 422 `INVALID_TRANSITION` (spec §3.3).
 */
class RetailOnlineController extends Controller
{
    public function __construct(private readonly RetailOnlineOrderService $orders) {}

    public function showSettings(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('view', RetailOnlineSettings::class);

        $settings = RetailOnlineSettings::query()
            ->where('company_id', (string) $actor->company_id)
            ->first();

        return response()->json([
            'data' => $settings instanceof RetailOnlineSettings
                ? $this->settingsPayload($settings)
                : [
                    'slug' => null,
                    'display_name' => null,
                    'description' => null,
                    'enabled' => false,
                    'location_id' => null,
                ],
        ]);
    }

    public function updateSettings(UpdateRetailOnlineSettingsRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('manage', RetailOnlineSettings::class);

        /** @var RetailOnlineSettings $settings */
        $settings = RetailOnlineSettings::query()->firstOrNew([
            'company_id' => (string) $actor->company_id,
        ]);

        $settings->fill([
            'slug' => $request->validated('slug'),
            'display_name' => $request->validated('display_name'),
            'description' => $request->validated('description'),
            'enabled' => (bool) $request->validated('enabled'),
            'location_id' => $request->validated('location_id'),
        ]);
        $settings->company_id = (string) $actor->company_id;
        $settings->save();

        return response()->json(['data' => $this->settingsPayload($settings->refresh())]);
    }

    /**
     * Commandes web du tenant (source=online), filtre optionnel
     * `fulfillment_status`.
     */
    public function orders(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailOrder::class);

        $query = RetailOrder::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('source', RetailOrderSource::Online);

        if ($request->filled('fulfillment_status')) {
            $query->where('fulfillment_status', (string) $request->input('fulfillment_status'));
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        /** @var list<RetailOrder> $items */
        $items = $orders->items();

        return response()->json([
            'data' => collect($items)
                ->map(fn (RetailOrder $order): array => $this->orderPayload($order))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function order(Request $request, RetailOrder $order): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id || $order->source !== RetailOrderSource::Online) {
            abort(404);
        }

        $this->authorize('view', $order);

        return response()->json(['data' => $this->orderPayload($order, withItems: true)]);
    }

    public function confirm(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Confirmed);
    }

    public function ready(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Ready);
    }

    public function ship(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Shipped);
    }

    public function deliver(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Delivered);
    }

    public function cancel(Request $request, RetailOrder $order): JsonResponse
    {
        return $this->transition($request, $order, RetailFulfillmentStatus::Cancelled);
    }

    private function transition(Request $request, RetailOrder $order, RetailFulfillmentStatus $target): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($order->company_id !== (string) $actor->company_id || $order->source !== RetailOrderSource::Online) {
            abort(404);
        }

        $this->authorize('fulfill', $order);

        try {
            $updated = $this->orders->transition($order, $target, (int) $actor->id);
        } catch (InvalidRetailFulfillmentTransition $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'INVALID_TRANSITION',
            ], 422);
        }

        return response()->json(['data' => $this->orderPayload($updated, withItems: true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(RetailOnlineSettings $settings): array
    {
        return [
            'slug' => $settings->slug,
            'display_name' => $settings->display_name,
            'description' => $settings->description,
            'enabled' => $settings->enabled,
            'location_id' => $settings->location_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(RetailOrder $order, bool $withItems = false): array
    {
        $payload = [
            'id' => (int) $order->id,
            'reference' => $order->reference,
            'fulfillment_status' => $order->fulfillment_status?->value,
            'status' => $order->status->value,
            'subtotal_minor' => (int) $order->subtotal_minor,
            'discount_minor' => (int) $order->discount_minor,
            'total_minor' => (int) $order->total_minor,
            'currency' => $order->currency,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'delivery_address' => $order->delivery_address,
            'delivery_city' => $order->delivery_city,
            'delivery_note' => $order->delivery_note,
            'created_at' => $order->created_at?->toIso8601String(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
        ];

        if ($withItems) {
            $payload['items'] = RetailOrderItem::query()
                ->where('company_id', (string) $order->company_id)
                ->where('order_id', (int) $order->id)
                ->orderBy('line_index')
                ->get()
                ->map(fn (RetailOrderItem $item): array => [
                    'product_id' => (int) $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price_minor' => (int) $item->unit_price_minor,
                    'line_total_minor' => (int) $item->line_total_minor,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }
}
