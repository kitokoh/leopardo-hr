<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;

/**
 * RESTO-606 (#6211) — Policy des comptes fidélité client RestaurantManager.
 *
 * L'opt-in (création de compte) et l'échange de points relèvent du pilotage
 * opérationnel (principal, rh, manager) ; lecture ouverte au tenant.
 */
class RestaurantLoyaltyCustomerPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantLoyaltyCustomer $customer): bool
    {
        return $customer->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function redeem(Employee $actor, RestaurantLoyaltyCustomer $customer): bool
    {
        return $this->create($actor) && $customer->company_id === $actor->company_id;
    }
}
