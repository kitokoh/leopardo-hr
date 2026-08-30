<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Source de prise d'une commande restaurant (BC-25, RESTO-214, issue #6179).
 */
enum OrderSource: string
{
    case POS = 'pos';
    case WEB = 'web';
    case PHONE = 'phone';
    case DELIVERY_APP = 'delivery_app';
    case KIOSK = 'kiosk';

    public function label(): string
    {
        return match ($this) {
            self::POS => 'Caisse (POS)',
            self::WEB => 'Web',
            self::PHONE => 'Téléphone',
            self::DELIVERY_APP => 'Application de livraison',
            self::KIOSK => 'Kiosque libre-service',
        };
    }
}
