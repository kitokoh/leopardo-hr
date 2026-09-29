<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleAlert;

/**
 * Cas d'usage « acquitter une alerte vehicule » (BOS-024g, #8218).
 *
 * Extrait de `VehicleAlertController::acknowledge` : horodatage implicite de
 * l'acquittement par l'auteur (`acknowledged_by` = employe connecte) et
 * retour du modele rafraichi pour la ressource de reponse.
 */
final class AcknowledgeVehicleAlertAction
{
    public function execute(VehicleAlert $alert, int $employeeId): VehicleAlert
    {
        $alert->update([
            'acknowledged' => true,
            'acknowledged_by' => $employeeId,
        ]);

        /** @var VehicleAlert $fresh */
        $fresh = $alert->fresh();

        return $fresh;
    }
}
