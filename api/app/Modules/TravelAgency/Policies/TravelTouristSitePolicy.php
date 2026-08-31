<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;

/**
 * TRAVEL-909 (#6112) — Policy de l'annuaire des sites touristiques.
 * Lecture : tenant ; écriture : rôles opérationnels de l'agence.
 */
final class TravelTouristSitePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelTouristSite $site): bool
    {
        return $site->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function update(Employee $actor, TravelTouristSite $site): bool
    {
        return $this->create($actor) && $site->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelTouristSite $site): bool
    {
        return $this->update($actor, $site);
    }
}
