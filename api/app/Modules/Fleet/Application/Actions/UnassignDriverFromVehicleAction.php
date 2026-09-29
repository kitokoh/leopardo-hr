<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;

/**
 * Cas d'usage « retirer le conducteur courant d'un vehicule » (BOS-024g, #8218).
 *
 * Extrait de `VehicleController::unassign` : fermeture de l'affectation
 * ouverte la plus recente (`end_date`) puis detachement du conducteur. Un
 * vehicule sans affectation ouverte est un no-op, comme avant extraction.
 */
final class UnassignDriverFromVehicleAction
{
    public function execute(Vehicle $vehicle): void
    {
        $currentAssignment = $vehicle->assignments()
            ->whereNull('end_date')
            ->latest('start_date')
            ->first();

        if ($currentAssignment !== null) {
            $currentAssignment->update(['end_date' => now()->toDateString()]);
        }

        $vehicle->update(['assigned_driver_id' => null]);
    }
}
