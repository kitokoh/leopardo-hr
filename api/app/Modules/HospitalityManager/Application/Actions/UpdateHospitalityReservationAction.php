<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;

/**
 * Modification d'une réservation (BOS-024d, #8215) — extraite de
 * HospitalityReservationController::update. Comportement conservé à
 * l'identique : anti-overbooking re-vérifié sur l'état modifié (409
 * HOSPITALITY_NO_AVAILABILITY), réservation en état terminal non
 * modifiable (cf. HospitalityReservationService).
 */
final class UpdateHospitalityReservationAction
{
    public function __construct(private readonly HospitalityReservationService $reservations) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (UpdateHospitalityReservationRequest)
     */
    public function execute(HospitalityReservation $reservation, array $validated): HospitalityReservation
    {
        return $this->reservations->updateReservation($reservation, $validated);
    }
}
