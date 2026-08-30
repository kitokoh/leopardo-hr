<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryAppConfig;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-806 (#6227) — traitement des webhooks des apps de livraison.
 *
 * - Résolution du tenant : par (provider, external_restaurant_id) depuis la
 *   configuration de la marketplace (O(1), aucune itération) ;
 * - Signature HMAC vérifiée avec le secret du tenant (401 sinon) ;
 * - Normalisation via l'adaptateur, puis création de la commande avec le
 *   MÊME workflow interne (RestaurantPublicOrderService, source
 *   delivery_app) — idempotente par idempotency_key
 *   `delivery-{provider}-{external_order_id}` (rejeu webhook sans doublon).
 */
final class RestaurantDeliveryWebhookService
{
    /** @var array<string, DeliveryAppAdapter> */
    private array $adapters = [];

    public function __construct(
        private readonly RestaurantPublicOrderService $publicOrders,
        DeliveryAppAdapter ...$adapters,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->providerCode()] = $adapter;
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(string $provider, string $rawBody, string $signature, array $payload): RestaurantOrder
    {
        $adapter = $this->adapters[$provider] ?? null;

        if ($adapter === null) {
            abort(404, 'DELIVERY_APP_PROVIDER_UNKNOWN');
        }

        $normalized = $adapter->parseInbound($payload);

        abort_if($normalized->externalOrderId === '', 422, 'DELIVERY_APP_ORDER_ID_MISSING');

        $config = RestaurantDeliveryAppConfig::query()
            ->where('provider', $provider)
            ->where('external_restaurant_id', $normalized->externalRestaurantId)
            ->where('enabled', true)
            ->first();

        if (! $config instanceof RestaurantDeliveryAppConfig) {
            abort(404, 'DELIVERY_APP_RESTAURANT_UNKNOWN');
        }

        $secret = (string) ($config->webhook_secret_encrypted ?? '');

        abort_if($secret === '' || ! $adapter->verifySignature($rawBody, $signature, $secret), 401, 'DELIVERY_APP_SIGNATURE_INVALID');

        $items = $this->normalizeItems($normalized, (string) $config->company_id);

        $order = $this->publicOrders->createOrder((string) $config->company_id, [
            'branch_id' => $this->firstBranchId((string) $config->company_id),
            'order_type' => $normalized->orderType,
            'items' => $items,
            'customer_name' => $normalized->customerName,
            'customer_phone' => $normalized->customerPhone,
            'note' => $normalized->note,
            'idempotency_key' => 'delivery-'.$provider.'-'.$normalized->externalOrderId,
            'source' => 'delivery_app',
        ]);

        return $order;
    }

    /**
     * @return list<array{menu_item_id: int, quantity: float|string}>
     */
    private function normalizeItems(DeliveryAppOrderPayload $payload, string $companyId): array
    {
        // La marketplace référence des products internes (product_id) : on
        // retrouve un menu_item actif du MÊME tenant les exposant (même
        // workflow que le web).
        $items = [];

        foreach ($payload->items as $item) {
            $productId = (int) $item['product_id'];
            $menuItem = \App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem::query()
                ->where('company_id', $companyId)
                ->whereHas('product', function (\Illuminate\Database\Eloquent\Builder $query) use ($productId): \Illuminate\Database\Eloquent\Builder {
                    return $query->where('id', $productId);
                })
                ->whereHas('menu', function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                    return $query->where('status', 'active');
                })
                ->first();

            abort_if($menuItem === null, 422, 'DELIVERY_APP_PRODUCT_UNKNOWN:'.$productId);

            $items[] = [
                'menu_item_id' => (int) $menuItem->getAttribute('id'),
                'quantity' => $item['quantity'],
            ];
        }

        abort_if($items === [], 422, 'DELIVERY_APP_NO_ITEMS');

        return $items;
    }

    private function firstBranchId(string $companyId): int
    {
        $branch = \App\Modules\RestaurantManager\Domain\Models\RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        abort_if($branch === null, 422, 'DELIVERY_APP_NO_ACTIVE_BRANCH');

        return (int) $branch->getAttribute('id');
    }
}
