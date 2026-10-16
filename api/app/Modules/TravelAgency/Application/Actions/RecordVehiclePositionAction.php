<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;

/**
 * #8540 (TRAVEL-OS) — enregistrement de la DERNIÈRE POSITION CONNUE d'un
 * véhicule.
 *
 * Aucun fournisseur GPS n'est intégré : cette action ne fait qu'accepter une
 * position transmise par un dispositif autorisé (boîtier GPS ou application).
 * Elle n'invente jamais de coordonnées — sans transmission, le véhicule reste
 * sans position connue.
 */
final class RecordVehiclePositionAction
{
    public function execute(
        TravelVehicle $vehicle,
        float $latitude,
        float $longitude,
        TravelVehiclePositionSource $source,
    ): TravelVehicle {
        $vehicle->forceFill([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'position_source' => $source,
            'position_recorded_at' => now(),
        ])->save();

        return $vehicle->refresh();
    }
}
