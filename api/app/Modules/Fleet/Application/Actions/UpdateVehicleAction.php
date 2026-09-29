<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;

/**
 * Cas d'usage « mettre a jour un vehicule » (BOS-024g, #8218).
 *
 * Extrait de `VehicleController::update` : mise a jour partielle (payload
 * valide), retour du modele rafraichi pour la reponse HTTP.
 */
final class UpdateVehicleAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (VehicleController::update).
     */
    public function execute(Vehicle $vehicle, array $payload): Vehicle
    {
        $vehicle->update($payload);

        return $vehicle->refresh();
    }
}
