<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

/**
 * RESTO-407 (#6194) — Signature HMAC des callbacks de paiement.
 *
 * Le callback de confirmation mobile money est authentifié par une signature
 * HMAC-SHA256 (en-tête `X-Leopardo-Signature: sha256=<hex>`). Secret :
 * - `RESTAURANT_MOBILE_MONEY_WEBHOOK_SECRET` si configuré (env) ;
 * - sinon secret déterministe par tenant dérivé de APP_KEY
 *   (hash_hmac(company_id, APP_KEY)) — aucune donnée sensible en dur,
 *   secret distinct par tenant (spec §6.2).
 */
final class PaymentCallbackSigner
{
    public function secretFor(string $companyId): string
    {
        $configured = config('restaurantmanager.mobile_money.webhook_secret');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'restaurant-callback:'.$companyId, (string) config('app.key'));
    }

    public function sign(string $payload, string $companyId): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->secretFor($companyId));
    }

    /**
     * Vérification en temps constant (fail-closed : signature absente ou
     * invalide → false).
     */
    public function verify(string $payload, string $signature, string $companyId): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = $this->sign($payload, $companyId);

        return hash_equals($expected, $signature);
    }
}
