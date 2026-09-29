<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;

/**
 * Mise à jour d'une affectation staff (BOS-024d, #8215) — extraite de
 * HospitalityPropertyStaffController::update. Comportement conservé à
 * l'identique : l'affectation doit appartenir à l'établissement de la
 * route (404 sinon), puis mise à jour partielle et rechargement de
 * l'employé lié.
 */
final class UpdateHospitalityStaffAssignmentAction
{
    /**
     * @param  array<string, mixed>  $validated  Payload validé (UpdateHospitalityPropertyStaffRequest)
     */
    public function execute(
        HospitalityProperty $property,
        HospitalityPropertyStaff $assignment,
        array $validated,
    ): HospitalityPropertyStaff {
        // L'affectation doit appartenir à l'établissement de la route.
        abort_if((int) $assignment->getAttribute('property_id') !== $property->getKey(), 404);

        $assignment->update($validated);

        return $assignment->load('employee')->refresh();
    }
}
