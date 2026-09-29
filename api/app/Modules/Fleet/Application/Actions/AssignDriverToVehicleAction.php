<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAssignment;

/**
 * Cas d'usage « affecter un conducteur a un vehicule » (BOS-024g, #8218).
 *
 * Extrait de `VehicleController::assign` : journalisation de l'affectation
 * (historique daté, `created_by`) puis mise a jour du conducteur courant du
 * vehicule. L'existence de l'employe dans la societe du vehicule est verifiee
 * par la validation HTTP (regle `exists` scoping tenant, #4788).
 */
final class AssignDriverToVehicleAction
{
    public function execute(
        Vehicle $vehicle,
        string $companyId,
        int $employeeId,
        string $startDate,
        ?string $reason = null,
        ?int $createdBy = null
    ): VehicleAssignment {
        /** @var VehicleAssignment $assignment */
        $assignment = $vehicle->assignments()->create([
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'start_date' => $startDate,
            'reason' => $reason,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        $vehicle->update(['assigned_driver_id' => $employeeId]);

        return $assignment;
    }
}
