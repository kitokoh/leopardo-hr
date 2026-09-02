<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;

/**
 * RBAC des clients fidélité (FUEL-016, #5810). deny-by-default : CRUD
 * manager ; lecture pour tout employé du tenant.
 */
class FuelCustomerPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelCustomer $customer): bool
    {
        return $customer->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelCustomer $customer): bool
    {
        return $actor->isManager() && $customer->company_id === (string) $actor->company_id;
    }
}
