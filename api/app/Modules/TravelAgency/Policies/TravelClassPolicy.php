<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelClass;

/**
 * TRAVEL-305 (#6035) — Policy des classes de service TravelAgency.
 *
 * Même schéma que les autres Policies référentielles du module :
 * `travel.manage` (création/modification/suppression) réservé à
 * principal/rh/manager, lecture ouverte à tout employé du tenant.
 */
class TravelClassPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelClass $travelClass): bool
    {
        return $travelClass->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelClass $travelClass): bool
    {
        return $this->create($actor) && $travelClass->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelClass $travelClass): bool
    {
        return $this->update($actor, $travelClass);
    }
}
