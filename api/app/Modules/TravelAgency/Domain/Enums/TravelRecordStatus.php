<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut générique des enregistrements du référentiel TravelAgency
 * (pays, villes, gares, bureaux, classes…).
 *
 * Travail issu de la numérisation des états de gv-back (int 1/2/3) vers des
 * enums typées — spec §2.4/A7.
 */
enum TravelRecordStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::DISABLED => 'Désactivé',
        };
    }
}
