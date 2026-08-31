<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;

/**
 * TRAVEL-321 (#6051) — Policy du catalogue hôtelier TravelAgency.
 *
 * Même schéma que `TravelVehiclePolicy` : `travel.manage`
 * (création/modification/suppression) réservé à principal/rh/manager,
 * lecture ouverte à tout employé du tenant.
 */
class TravelHotelPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelHotel $hotel): bool
    {
        return $hotel->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelHotel $hotel): bool
    {
        return $this->create($actor) && $hotel->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelHotel $hotel): bool
    {
        return $this->update($actor, $hotel);
    }
}
