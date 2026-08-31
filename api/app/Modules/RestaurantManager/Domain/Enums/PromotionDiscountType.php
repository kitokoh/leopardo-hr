<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Type de remise d'une promotion (BC-25, RESTO-214, issue #6179).
 */
enum PromotionDiscountType: string
{
    case PERCENT = 'percent';
    case AMOUNT = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::PERCENT => 'Pourcentage',
            self::AMOUNT => 'Montant fixe',
        };
    }
}
