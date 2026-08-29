<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;

/**
 * TRAVEL-303 (#6033) — Policy des bureaux de vente TravelAgency.
 *
 * Même schéma que `TravelStationPolicy` (TRAVEL-302) : `travel.manage`
 * (création/modification/suppression) réservé à principal/rh/manager,
 * lecture ouverte à tout employé du tenant.
 */
class TravelOfficePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelOffice $office): bool
    {
        return $office->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelOffice $office): bool
    {
        return $this->create($actor) && $office->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelOffice $office): bool
    {
        return $this->update($actor, $office);
    }
}
