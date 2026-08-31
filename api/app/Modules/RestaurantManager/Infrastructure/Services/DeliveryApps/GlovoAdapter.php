<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryAppConfig;

/**
 * RESTO-806 (#6227) — adaptateur Glovo.
 *
 * Signature : HMAC-SHA256 du corps brut, header `X-Glovo-Signature`
 * (secret par tenant).
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\ValueObjects\MarketplaceInboundOrder;
use App\Modules\RestaurantManager\Domain\ValueObjects\MarketplaceOrderItem;

/**
 * RESTO-806 (#6227) — Adapter Glovo.
 *
 * Signature : HMAC-SHA256 hex du corps brut dans `X-Glovo-Signature`
 * (comparaison constante, fail-closed). Parsing tolérant — le rapprochement
 * des produits se fait par `code` interne.
 */
final class GlovoAdapter implements DeliveryAppAdapter
{
    public function providerCode(): string
    {
        return RestaurantDeliveryAppConfig::PROVIDER_GLOVO;
    }

    public function verifySignature(string $rawBody, string $signature, string $secret): bool
    {
        return 'glovo';
    }

    public function inboundSignatureHeader(): string
    {
        return 'X-Glovo-Signature';
    }

    public function verifySignature(string $rawBody, string $signature, ?string $secret): bool
    {
        if ($secret === null || $secret === '' || $signature === '') {
            return false; // fail-closed
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function parseInbound(array $payload): DeliveryAppOrderPayload
    {
        $items = [];

        foreach ((array) ($payload['products'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['product_id'], $item['quantity'])) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
            ];
        }

        return new DeliveryAppOrderPayload(
            externalOrderId: (string) ($payload['external_order_id'] ?? ''),
            externalRestaurantId: (string) ($payload['restaurant_external_id'] ?? ''),
            orderType: 'delivery',
            items: $items,
            customerName: isset($payload['customer_name']) ? (string) $payload['customer_name'] : null,
            customerPhone: isset($payload['customer_phone']) ? (string) $payload['customer_phone'] : null,
            customerAddress: isset($payload['delivery_address']) ? (string) $payload['delivery_address'] : null,
            note: isset($payload['comments']) ? (string) $payload['comments'] : null,
        );
    }
    public function parseInboundOrder(string $rawBody): MarketplaceInboundOrder
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            abort(422, __('restaurant.marketplace.invalid_payload'));
        }

        $eventId = (string) (data_get($payload, 'order_id') ?? data_get($payload, 'id') ?? '');
        if ($eventId === '') {
            abort(422, __('restaurant.marketplace.missing_event_id'));
        }

        $customerName = trim((string) (data_get($payload, 'customer.name') ?? __('restaurant.marketplace.unknown_customer')));
        $customerPhone = data_get($payload, 'customer.phone') !== null
            ? (string) data_get($payload, 'customer.phone')
            : null;
        $currency = strtoupper((string) (data_get($payload, 'total.currency') ?? 'DZD'));
        $note = data_get($payload, 'notes') !== null ? (string) data_get($payload, 'notes') : null;
        $branchCode = data_get($payload, 'store_code') !== null ? (string) data_get($payload, 'store_code') : null;

        $rawItems = data_get($payload, 'products', data_get($payload, 'items', []));
        $items = [];

        foreach ((array) $rawItems as $raw) {
            $items[] = new MarketplaceOrderItem(
                externalProductId: (string) (data_get($raw, 'id') ?? data_get($raw, 'external_id') ?? ''),
                name: (string) (data_get($raw, 'name') ?? data_get($raw, 'title') ?? ''),
                quantity: (float) (data_get($raw, 'quantity') ?? 1),
                unitPriceMinor: data_get($raw, 'price') !== null ? (int) round((float) data_get($raw, 'price') * 100) : null,
            );
        }

        return new MarketplaceInboundOrder(
            eventId: $eventId,
            provider: $this->providerCode(),
            customerName: $customerName,
            customerPhone: $customerPhone,
            items: $items,
            currency: $currency,
            note: $note,
            branchCode: $branchCode,
        );
    }

    public function outboundStatusPayload(RestaurantOrder $order): array
    {
        return [
            'order_id' => $order->reference,
            'status' => strtoupper((string) $order->status->value),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
