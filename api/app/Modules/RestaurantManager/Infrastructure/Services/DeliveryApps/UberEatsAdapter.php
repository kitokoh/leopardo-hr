<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryAppConfig;

/**
 * RESTO-806 (#6227) — adaptateur Uber Eats.
 *
 * Signature : HMAC-SHA256 du corps brut, header `X-Uber-Signature`
 * (pattern webhooks REST, secret par tenant).
 */
final class UberEatsAdapter implements DeliveryAppAdapter
{
    public function providerCode(): string
    {
        return RestaurantDeliveryAppConfig::PROVIDER_UBER_EATS;
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

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['product_id'], $item['quantity'])) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
            ];
        }

        return new DeliveryAppOrderPayload(
            externalOrderId: (string) ($payload['order_id'] ?? ''),
            externalRestaurantId: (string) ($payload['restaurant_id'] ?? ''),
            orderType: 'delivery',
            items: $items,
            customerName: isset($payload['customer']['name']) ? (string) $payload['customer']['name'] : null,
            customerPhone: isset($payload['customer']['phone']) ? (string) $payload['customer']['phone'] : null,
            customerAddress: isset($payload['delivery']['address']) ? (string) $payload['delivery']['address'] : null,
            note: isset($payload['note']) ? (string) $payload['note'] : null,
        );
    }
}
