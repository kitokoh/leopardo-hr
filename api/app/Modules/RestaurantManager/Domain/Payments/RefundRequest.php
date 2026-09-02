<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Payments;

/**
 * RESTO-406 (#6193) — Requête de remboursement auprès d'une passerelle.
 */
final class RefundRequest
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $reasonCode,
    ) {}
}
