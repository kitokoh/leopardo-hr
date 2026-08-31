<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelProduct;

/**
 * RBAC du catalogue produits — FUEL-011 (#5805). deny-by-default : CRUD
 * manager, lecture employé du tenant.
 * RBAC du catalogue produits FuelStation (FUEL-011, #5805).
 *
 * Manager uniquement (le catalogue alimente les pompes, cuves et ventes).
 */
class FuelProductPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id;
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelProduct $product): bool
    {
        return $actor->isManager() && $product->company_id === (string) $actor->company_id;
        return $actor->isManager();
    }
}
