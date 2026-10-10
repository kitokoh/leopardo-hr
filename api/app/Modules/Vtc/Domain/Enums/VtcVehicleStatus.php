<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Enums;

/**
 * Statut opérationnel d'un véhicule VTC (BC-34 VTC, VTC-02/#8358) :
 * `active` (affectable à un chauffeur) ou `inactive` (retiré temporairement
 * ou définitivement de la flotte).
 */
enum VtcVehicleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
