<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Motif d'un mouvement de points de fidélité (BC-25, RESTO-214, issue #6179).
 */
enum LoyaltyPointsReason: string
{
    case EARN = 'earn';
    case REDEEM = 'redeem';
    case ADJUST = 'adjust';
    case EXPIRE = 'expire';

    public function label(): string
    {
        return match ($this) {
            self::EARN => 'Gain',
            self::REDEEM => 'Utilisation',
            self::ADJUST => 'Ajustement',
            self::EXPIRE => 'Expiration',
        };
    }
}
