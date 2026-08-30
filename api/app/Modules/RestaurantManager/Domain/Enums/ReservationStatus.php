<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'une réservation (BC-25, RESTO-214, issue #6179).
 */
enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SEATED = 'seated';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::SEATED => 'Installée',
            self::COMPLETED => 'Terminée',
            self::NO_SHOW => 'Non venue',
            self::CANCELLED => 'Annulée',
        };
    }
}
