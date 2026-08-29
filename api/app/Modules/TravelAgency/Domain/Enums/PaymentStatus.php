<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut de paiement (TRAVEL-209/#6022 — `travel_bookings.payment_status` ;
 * TRAVEL-210/#6023 — `travel_payments.status`).
 *
 * Enum partagée entre les deux tables : le statut agrégé d'une réservation
 * et le statut d'une tentative de paiement individuelle relèvent du même
 * vocabulaire.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmé',
            self::FAILED => 'Échoué',
            self::REFUNDED => 'Remboursé',
        };
    }
}
