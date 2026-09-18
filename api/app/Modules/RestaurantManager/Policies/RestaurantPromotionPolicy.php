<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-607 (#6212) — Policy des promotions.
 *
 * Lecture : tout employé du tenant. Écriture : `principal`/`rh`/`manager`.
 */
class RestaurantPromotionPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $promo->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $promo->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $promo->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $promo->branch_id);
    }

    public function delete(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $this->update($actor, $promo);
    }
}
