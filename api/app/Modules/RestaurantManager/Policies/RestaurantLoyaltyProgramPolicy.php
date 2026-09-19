<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-606 (#6211) — Policy du programme fidélité RestaurantManager.
 *
 * Le programme (configuration : taux de points, taux d'échange) est réservé
 * au gérant/propriétaire (principal, rh) ; lecture ouverte au tenant.
 */
class RestaurantLoyaltyProgramPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantLoyaltyProgram $program): bool
    {
        return $program->company_id === $actor->company_id;
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantLoyaltyProgram $program): bool
    {
        return $program->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, null);
    }

    public function delete(Employee $actor, RestaurantLoyaltyProgram $program): bool
    {
        return $this->update($actor, $program);
    }
}
