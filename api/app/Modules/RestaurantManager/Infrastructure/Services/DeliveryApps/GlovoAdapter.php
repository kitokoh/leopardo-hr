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
 */
final class GlovoAdapter implements DeliveryAppAdapter
{
    public function providerCode(): string
    {
        return RestaurantDeliveryAppConfig::PROVIDER_GLOVO;
    }

    public function verifySignature(string $rawBody, string $signature, string $secret): bool
    {
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
}
