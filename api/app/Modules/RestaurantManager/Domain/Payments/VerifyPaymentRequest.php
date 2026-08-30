<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Payments;

/**
 * RESTO-406 (#6193) — Requête de vérification d'un paiement auprès d'une
 * passerelle (ou d'un callback signé, RESTO-407).
 */
final class VerifyPaymentRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $companyId,
        public readonly string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly array $payload = [],
    ) {}
}
