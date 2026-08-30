<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;

/**
 * TRAVEL-308 (#6038) — Policy des trajets datés TravelAgency.
 *
 * Même schéma que `TravelRoutePolicy` : `travel.manage` réservé à
 * principal/rh/manager ; lecture ouverte à tout employé du tenant.
 * Les transitions de statut (publish/cancel) suivent `travel.manage`
 * (via update) — le workflow lui-même est validé par les Actions.
 */
class TravelTripPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelTrip $trip): bool
    {
        return $trip->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelTrip $trip): bool
    {
        return $this->create($actor) && $trip->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelTrip $trip): bool
    {
        return $this->update($actor, $trip);
    }
}
