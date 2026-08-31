<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Payments;

use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;

/**
 * RESTO-406 (#6193) — Résultat d'un remboursement.
 */
final class RefundResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
    ) {
    }
}
