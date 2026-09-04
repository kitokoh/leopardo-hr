<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;

/**
 * TRAVEL-319 (#6049) — Policy des véhicules de location TravelAgency.
 *
 * Même schéma que `TravelVehiclePolicy` : `travel.manage`
 * (création/modification/suppression) réservé à principal/rh/manager,
 * lecture ouverte à tout employé du tenant.
 */
class TravelRentalVehiclePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelRentalVehicle $vehicle): bool
    {
        return $vehicle->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelRentalVehicle $vehicle): bool
    {
        return $this->create($actor) && $vehicle->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelRentalVehicle $vehicle): bool
    {
        return $this->update($actor, $vehicle);
    }
}
