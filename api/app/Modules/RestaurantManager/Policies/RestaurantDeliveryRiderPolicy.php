<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-605 (#6210) — Policy des livreurs.
 *
 * Lecture : tout employé du tenant. Écriture : `principal`/`rh`/`manager`.
 */
class RestaurantDeliveryRiderPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $rider->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $rider->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $rider->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $rider->branch_id);
    }

    public function delete(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $this->update($actor, $rider);
    }
}
