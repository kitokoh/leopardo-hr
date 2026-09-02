<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Type de compagnie de transport (TRAVEL-204, issue #6017).
 */
enum CarrierType: string
{
    case BUS = 'bus';
    case TRAIN = 'train';
    case PLANE = 'plane';
    case BOAT = 'boat';

    public function label(): string
    {
        return match ($this) {
            self::BUS => 'Bus',
            self::TRAIN => 'Train',
            self::PLANE => 'Avion',
            self::BOAT => 'Bateau',
        };
    }
}
