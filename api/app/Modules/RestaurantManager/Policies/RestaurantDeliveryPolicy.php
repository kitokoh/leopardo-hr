<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-605 (#6210) — Policy des livraisons.
 *
 * Lecture : tout employé du tenant. Écriture (cycle) : `principal`/`rh`/
 * `manager` ; le livreur (`rider`) lit ses tournées.
 */
class RestaurantDeliveryPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $delivery->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $delivery->order?->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $delivery->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $delivery->order?->branch_id);
    }

    public function transition(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $delivery->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $delivery->order?->branch_id);
    }
}
