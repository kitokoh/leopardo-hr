<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Canal d'origine d'une réservation (TRAVEL-209, issue #6022).
 */
enum BookingSource: string
{
    case ONLINE = 'online';
    case OFFICE = 'office';
    case PHONE = 'phone';
    case PARTNER = 'partner';
    // #7737 — achat via la marketplace publique inter-agences (tenant résolu
    // par trajet, sans jeton boutique d'agence).
    case MARKETPLACE = 'marketplace';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'En ligne',
            self::OFFICE => 'Guichet',
            self::PHONE => 'Téléphone',
            self::PARTNER => 'Partenaire',
            self::MARKETPLACE => 'Marketplace',
        };
    }
}
