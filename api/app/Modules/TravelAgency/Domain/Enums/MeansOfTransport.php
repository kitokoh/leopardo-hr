<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Moyen de transport d'un trajet daté (TRAVEL-207, issue #6020).
 *
 * Distinct de `CarrierType` (type de compagnie) : un trajet garde son moyen
 * de transport même si `carrier_id`/`vehicle_id` sont nuls (préparation).
 */
enum MeansOfTransport: string
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
