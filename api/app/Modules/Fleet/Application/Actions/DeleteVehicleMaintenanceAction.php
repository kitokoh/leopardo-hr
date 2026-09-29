<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleMaintenance;

/**
 * Cas d'usage « supprimer une intervention de maintenance » (BOS-024g, #8218).
 *
 * Extrait de `VehicleMaintenanceController::destroy`.
 */
final class DeleteVehicleMaintenanceAction
{
    public function execute(VehicleMaintenance $record): void
    {
        $record->delete();
    }
}
