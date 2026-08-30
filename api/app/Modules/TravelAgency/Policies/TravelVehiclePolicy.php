<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;

/**
 * TRAVEL-306 (#6036) — Policy de la flotte propre TravelAgency.
 *
 * Même schéma que `TravelCarrierPolicy` : `travel.manage`
 * (création/modification/suppression) réservé à principal/rh/manager,
 * lecture ouverte à tout employé du tenant.
 */
class TravelVehiclePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelVehicle $vehicle): bool
    {
        return $vehicle->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelVehicle $vehicle): bool
    {
        return $this->create($actor) && $vehicle->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelVehicle $vehicle): bool
    {
        return $this->update($actor, $vehicle);
    }
}
