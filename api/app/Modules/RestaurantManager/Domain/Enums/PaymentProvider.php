<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Moyen de paiement accepté en restauration (BC-25, RESTO-214, issue #6179).
 */
enum PaymentProvider: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case MOBILE_MONEY = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Espèces',
            self::CARD => 'Carte bancaire',
            self::MOBILE_MONEY => 'Mobile money',
        };
    }
}
