<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Type d'une commande restaurant (BC-25, RESTO-214, issue #6179).
 */
enum OrderType: string
{
    case DINE_IN = 'dine_in';
    case TAKEAWAY = 'takeaway';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DINE_IN => 'Sur place',
            self::TAKEAWAY => 'À emporter',
            self::DELIVERY => 'Livraison',
        };
    }
}
