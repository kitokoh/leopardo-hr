<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;

/**
 * TRAVEL-307 (#6037) — Policy des routes ville→ville TravelAgency.
 *
 * Même schéma que `TravelCarrierPolicy` : `travel.manage`
 * (création/modification/suppression) réservé à principal/rh/manager,
 * lecture ouverte à tout employé du tenant.
 */
class TravelRoutePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelRoute $route): bool
    {
        return $route->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelRoute $route): bool
    {
        return $this->create($actor) && $route->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelRoute $route): bool
    {
        return $this->update($actor, $route);
    }
}
