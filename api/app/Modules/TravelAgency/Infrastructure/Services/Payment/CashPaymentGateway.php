<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services\Payment;

use App\Modules\TravelAgency\Domain\Contracts\PaymentGatewayInterface;

/**
 * TRAVEL-406 (#6058) — Passerelle de paiement comptant (guichet).
 *
 * La confirmation manuelle est portée par un agent autorisé : `initiate()`
 * enregistre une référence locale et retourne un statut `pending` ; la
 * confirmation se fait via le workflow booking (ConfirmBookingAction),
 * jamais par un appel externe. `verify()` reflète l'état local.
 */
final class CashPaymentGateway implements PaymentGatewayInterface
{
    public function initiate(array $request): array
    {
        return [
            'provider_reference' => 'CASH-'.strtoupper(bin2hex(random_bytes(6))),
            'redirect_url' => null,
            'status' => 'pending',
        ];
    }

    public function verify(string $providerReference): array
    {
        return ['status' => 'pending'];
    }

    public function refund(string $providerReference): array
    {
        return ['status' => 'refunded', 'refunded_at' => now()->toIso8601String()];
    }
}
