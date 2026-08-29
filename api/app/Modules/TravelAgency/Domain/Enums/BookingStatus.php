<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'une réservation (TRAVEL-209, issue #6022).
 */
enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::CANCELLED => 'Annulée',
            self::REFUNDED => 'Remboursée',
            self::COMPLETED => 'Terminée',
        };
    }
}
