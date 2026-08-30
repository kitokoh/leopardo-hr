<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Payments;

/**
 * RESTO-406 (#6193) — Requête d'initiation d'un paiement vers une passerelle.
 *
 * Montants en minor units ; `reference` est la référence de la commande
 * (traçabilité) ; `idempotencyKey` garantit un rejeu sans doublon.
 */
final class InitiatePaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $companyId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $reference,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {}
}
