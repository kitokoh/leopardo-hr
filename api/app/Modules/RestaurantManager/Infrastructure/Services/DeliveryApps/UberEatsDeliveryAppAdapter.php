<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;

/**
 * RESTO-806 (#6227) — Adaptateur Uber Eats (webhooks entrants).
 *
 * Signature HMAC-SHA256 (en-tête `X-Uber-Signature`), secret configuré par
 * tenant via `restaurantmanager.delivery_apps.uber_eats.webhook_secret`
 * (env) ; à défaut, secret déterministe dérivé de APP_KEY (jamais en dur).
 * Les articles sont normalisés par `code` (code produit Leopardo) ; la
 * quantité est bornée (1..99) avant d'entrer dans le workflow interne.
 */
final class UberEatsDeliveryAppAdapter implements DeliveryAppAdapter
{
    public function providerCode(): string
    {
        return 'uber_eats';
    }

    public function verifySignature(string $payload, string $signature, string $companyId): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload, $companyId), $signature);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{product_code: string, quantity: float|string}>
     */
    public function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $code = isset($item['code']) && is_string($item['code']) ? $item['code'] : '';

            if ($code === '') {
                continue;
            }

            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;
            $quantity = max(1.0, min(99.0, $quantity));

            $normalized[] = ['product_code' => $code, 'quantity' => $quantity];
        }

        return $normalized;
    }

    private function sign(string $payload, string $companyId): string
    {
        return hash_hmac('sha256', $payload, $this->secretFor($companyId));
    }

    private function secretFor(string $companyId): string
    {
        $configured = config('restaurantmanager.delivery_apps.uber_eats.webhook_secret');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'uber-eats:'.$companyId, (string) config('app.key'));
    }

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
