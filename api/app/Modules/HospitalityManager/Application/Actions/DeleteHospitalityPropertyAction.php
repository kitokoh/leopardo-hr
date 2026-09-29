<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;

/**
 * Suppression d'un établissement (BOS-024d, #8215) — extraite de
 * HospitalityPropertyController::destroy. Comportement conservé à
 * l'identique : suppression bloquée tant que des types de chambres ou des
 * unités sont rattachés (422 HOSPITALITY_PROPERTY_IN_USE) — la cascade
 * silencieuse est interdite.
 */
final class DeleteHospitalityPropertyAction
{
    public function execute(HospitalityProperty $property): void
    {
        // Suppression bloquée tant que des types de chambres ou des unités
        // sont rattachés à l'établissement (même invariant que le référentiel
        // HealthManager — suppression en cascade silencieuse interdite).
        if ($property->roomTypes()->exists() || $property->units()->exists()) {
            abort(422, 'HOSPITALITY_PROPERTY_IN_USE');
        }

        $property->delete();
    }
}
