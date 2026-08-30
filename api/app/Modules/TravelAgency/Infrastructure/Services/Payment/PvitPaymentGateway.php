<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services\Payment;

use App\Modules\TravelAgency\Domain\Contracts\PaymentGatewayInterface;

/**
 * TRAVEL-407 (#6059) — Passerelle mobile money PVIT (mode sandbox).
 *
 * Identifiants en config/env (`travel.payments.pvit.*`), jamais en dur.
 * En sandbox, `initiate()` simule un paiement accepté avec une référence
 * provider ; `verify()` confirme le statut `confirmed` (re-conciliation
 * active). Le contrat reste identique pour l'adaptateur production.
 */
final class PvitPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly array $config) {}

    public function initiate(array $request): array
    {
        // Sandbox : simulation d'une initiation acceptée.
        $merchant = $this->config['merchant_tel'] ?? 'sandbox';

        return [
            'provider_reference' => 'PVIT-'.strtoupper(bin2hex(random_bytes(8))),
            'redirect_url' => null,
            'status' => 'pending',
            'sandbox_merchant' => $merchant,
        ];
    }

    public function verify(string $providerReference): array
    {
        // Sandbox : tout paiement initié est considéré confirmé.
        return ['status' => 'confirmed', 'paid_at' => now()->toIso8601String()];
    }

    public function refund(string $providerReference): array
    {
        return ['status' => 'refunded', 'refunded_at' => now()->toIso8601String()];
    }
}
