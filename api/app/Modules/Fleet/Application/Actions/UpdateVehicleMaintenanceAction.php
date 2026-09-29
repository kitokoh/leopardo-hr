<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleMaintenance;

/**
 * Cas d'usage « mettre a jour une intervention de maintenance » (BOS-024g, #8218).
 *
 * Extrait de `VehicleMaintenanceController::update` : mise a jour partielle,
 * retour du modele rafraichi pour la reponse HTTP.
 */
final class UpdateVehicleMaintenanceAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (VehicleMaintenanceController::update).
     */
    public function execute(VehicleMaintenance $record, array $payload): VehicleMaintenance
    {
        $record->update($payload);

        return $record->refresh();
    }
}
