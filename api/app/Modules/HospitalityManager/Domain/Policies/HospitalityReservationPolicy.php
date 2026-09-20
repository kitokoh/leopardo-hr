<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-004 (#7946, BC-32) — Policy des réservations.
 *
 * Lecture = `view` sur l'établissement ; création guichet et transitions
 * (confirm/check-in/check-out/cancel/no-show) = geste opérationnel
 * `operate` ; édition des données (dates, type, montant) = `manage`.
 * Scoping progressif — voir le trait. Cross-tenant → refus (fail-closed).
 */
class HospitalityReservationPolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $reservation->getAttribute('property_id'));
    }

    /**
     * Création au guichet sur un établissement donné : geste opérationnel.
     */
    public function create(Employee $actor, int $propertyId): bool
    {
        return $this->canOperatePropertyResource($actor, $propertyId);
    }

    /**
     * Édition des données de la réservation (dates, type, montant, notes) :
     * geste de gestion.
     */
    public function update(Employee $actor, HospitalityReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $reservation->getAttribute('property_id'));
    }

    /**
     * Transitions du cycle de vie (confirm, check-in, check-out, cancel,
     * no-show) : geste opérationnel de guichet.
     */
    public function transition(Employee $actor, HospitalityReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id
            && $this->canOperatePropertyResource($actor, $reservation->getAttribute('property_id'));
    }
}
