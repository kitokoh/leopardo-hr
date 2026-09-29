<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;

/**
 * Suppression d'un type de chambre (BOS-024d, #8215) — extraite de
 * HospitalityRoomTypeController::destroy. Comportement conservé à
 * l'identique : un type rattaché à des unités n'est pas supprimable
 * silencieusement (422 HOSPITALITY_ROOM_TYPE_IN_USE).
 */
final class DeleteHospitalityRoomTypeAction
{
    public function execute(HospitalityRoomType $roomType): void
    {
        // Un type rattaché à des unités n'est pas supprimable silencieusement.
        if ($roomType->units()->exists()) {
            abort(422, 'HOSPITALITY_ROOM_TYPE_IN_USE');
        }

        $roomType->delete();
    }
}
