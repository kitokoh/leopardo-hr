<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;

/**
 * Cas d'usage « supprimer un vehicule » (BOS-024g, #8218).
 *
 * Extrait de `VehicleController::destroy`. Le message de reponse
 * (`errors.VEHICLE_DELETED`, #4812) reste compose par la couche HTTP.
 */
final class DeleteVehicleAction
{
    public function execute(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }
}
