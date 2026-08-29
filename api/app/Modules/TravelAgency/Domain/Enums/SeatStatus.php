<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'un siège de l'inventaire d'un trajet (TRAVEL-208, issue #6021).
 */
enum SeatStatus: string
{
    case FREE = 'free';
    case RESERVED = 'reserved';
    case SOLD = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Libre',
            self::RESERVED => 'Réservé',
            self::SOLD => 'Vendu',
        };
    }
}
