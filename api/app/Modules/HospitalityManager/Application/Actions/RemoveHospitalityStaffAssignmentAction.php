<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;

/**
 * Retrait d'une affectation staff (BOS-024d, #8215) — extrait de
 * HospitalityPropertyStaffController::destroy. Comportement conservé à
 * l'identique : l'affectation doit appartenir à l'établissement de la
 * route (404 sinon), retrait = soft delete (la ré-affectation ultérieure
 * restaurera la ligne, jamais de doublon physique).
 */
final class RemoveHospitalityStaffAssignmentAction
{
    public function execute(HospitalityProperty $property, HospitalityPropertyStaff $assignment): void
    {
        // L'affectation doit appartenir à l'établissement de la route.
        abort_if((int) $assignment->getAttribute('property_id') !== $property->getKey(), 404);

        $assignment->delete();
    }
}
