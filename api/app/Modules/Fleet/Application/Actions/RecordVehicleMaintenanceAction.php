<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleMaintenance;

/**
 * Cas d'usage « enregistrer une intervention de maintenance » (BOS-024g, #8218).
 *
 * Extrait de `VehicleMaintenanceController::store` : company_id issu de la
 * session, le suivi des prochaines echeances (date / kilometrage) fait partie
 * du meme enregistrement.
 */
final class RecordVehicleMaintenanceAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (VehicleMaintenanceController::store).
     */
    public function execute(string $companyId, array $payload): VehicleMaintenance
    {
        /** @var VehicleMaintenance $record */
        $record = VehicleMaintenance::query()->create(array_merge($payload, ['company_id' => $companyId]));

        return $record;
    }
}
