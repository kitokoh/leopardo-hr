<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Payments;

use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;

/**
 * RESTO-406 (#6193) — Résultat d'initiation d'un paiement.
 *
 * `providerReference` identifie la transaction chez la passerelle (nécessaire
 * à la vérification et au remboursement) ; `message` est un libellé sûr
 * (aucune stack trace, aucun secret).
 */
final class InitiatePaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
    ) {}
}
