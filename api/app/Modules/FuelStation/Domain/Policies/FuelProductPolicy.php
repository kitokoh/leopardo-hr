<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelProduct;

/**
 * RBAC du catalogue produits — FUEL-011 (#5805). deny-by-default : CRUD
 * manager, lecture employé du tenant.
 */
class FuelProductPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelProduct $product): bool
    {
        return $actor->isManager() && $product->company_id === (string) $actor->company_id;
    }
}
