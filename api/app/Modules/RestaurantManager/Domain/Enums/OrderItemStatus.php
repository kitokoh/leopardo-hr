<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'une ligne d'article de commande (BC-25, RESTO-214, issue #6179).
 */
enum OrderItemStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::CANCELLED => 'Annulé',
        };
    }
}
