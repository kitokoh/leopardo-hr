<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'une session de caisse (POS) (BC-25, RESTO-214, issue #6179).
 */
enum PosSessionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Ouverte',
            self::CLOSED => 'Clôturée',
            self::CANCELLED => 'Annulée',
        };
    }
}
