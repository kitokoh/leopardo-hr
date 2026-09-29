<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;

/**
 * Transition de statut d'une réservation (BOS-024d, #8215) — extraite de
 * HospitalityReservationController::transitionTo (confirm / check-in /
 * check-out / cancel / no-show). Comportement conservé à l'identique :
 * machine à états gardée (409 INVALID_RESERVATION_TRANSITION via
 * HospitalityInvalidTransitionException), re-vérification sous verrou,
 * capacité re-contrôlée à la confirmation, statut d'unité synchronisé au
 * check-in/check-out.
 */
final class TransitionHospitalityReservationAction
{
    public function __construct(private readonly HospitalityReservationService $reservations) {}

    public function execute(HospitalityReservation $reservation, string $target): HospitalityReservation
    {
        return $this->reservations->transition($reservation, $target);
    }
}
