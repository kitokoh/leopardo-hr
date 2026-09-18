<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-601 (#6206) — Policy des réservations.
 *
 * Lecture : tout employé du tenant. Création/modification/transitions :
 * `principal`/`rh`/`manager` (pilotage de la salle). L'annulation est
 * ouverte au même périmètre (la politique d'annulation est appliquée serveur).
 */
class RestaurantReservationPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $reservation->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $reservation->branch_id);
    }

    public function cancel(Employee $actor, RestaurantReservation $reservation): bool
    {
        return $this->update($actor, $reservation);
    }
}
