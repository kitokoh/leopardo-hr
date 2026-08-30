<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;

/**
 * RESTO-806 (#6227) — Adaptateur Glovo (webhooks entrants).
 *
 * Même contrat que l'adaptateur Uber Eats : signature HMAC-SHA256
 * (`X-Glovo-Signature`), secret par tenant en config/env (fallback
 * déterministe dérivé de APP_KEY), normalisation des articles par code
 * produit Leopardo avec quantité bornée.
 */
final class GlovoDeliveryAppAdapter implements DeliveryAppAdapter
{
    public function providerCode(): string
    {
        return 'glovo';
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
        $configured = config('restaurantmanager.delivery_apps.glovo.webhook_secret');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'glovo:'.$companyId, (string) config('app.key'));
    }
}
