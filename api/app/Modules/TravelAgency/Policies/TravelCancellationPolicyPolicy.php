<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;

/**
 * TRAVEL-813 (#6103) — Policy des politiques d'annulation.
 *
 * Même schéma que les Policies référentielles du module : lecture ouverte
 * à tout employé du tenant, écriture réservée `travel.manage`
 * (principal/rh/manager), 404 sûr cross-tenant.
 */
class TravelCancellationPolicyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelCancellationPolicy $policy): bool
    {
        return $policy->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelCancellationPolicy $policy): bool
    {
        return $this->create($actor) && $policy->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelCancellationPolicy $policy): bool
    {
        return $this->update($actor, $policy);
    }
}
