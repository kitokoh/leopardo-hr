<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-606 (#6211) — Policy des comptes fidélité client RestaurantManager.
 *
 * L'opt-in (création de compte) et l'échange de points relèvent du pilotage
 * opérationnel (principal, rh, manager) ; lecture ouverte au tenant.
 */
class RestaurantLoyaltyCustomerPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantLoyaltyCustomer $customer): bool
    {
        return $customer->company_id === $actor->company_id;
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function redeem(Employee $actor, RestaurantLoyaltyCustomer $customer): bool
    {
        return $customer->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, null);
    }
}
