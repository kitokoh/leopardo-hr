<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;

/**
 * Suppression d'une unité (BOS-024d, #8215) — extraite de
 * HospitalityUnitController::destroy. Comportement conservé à l'identique :
 * une unité référencée par une réservation ACTIVE (statut non terminal)
 * n'est pas supprimable (422 HOSPITALITY_UNIT_IN_USE) — la réservation
 * pointerait dans le vide et l'occupation du jour deviendrait incohérente.
 */
final class DeleteHospitalityUnitAction
{
    public function execute(HospitalityUnit $unit, string $companyId): void
    {
        // Une unité référencée par une réservation ACTIVE (non terminale)
        // n'est pas supprimable : la réservation pointerait dans le vide et
        // l'occupation du jour deviendrait incohérente (même invariant que
        // `HOSPITALITY_PROPERTY_IN_USE` / `HOSPITALITY_ROOM_TYPE_IN_USE`).
        $activeReservations = HospitalityReservation::query()
            ->where('company_id', $companyId)
            ->where('unit_id', $unit->getKey())
            ->whereNotIn('status', HospitalityReservation::TERMINAL_STATUSES)
            ->exists();

        if ($activeReservations) {
            abort(422, 'HOSPITALITY_UNIT_IN_USE');
        }

        $unit->delete();
    }
}
