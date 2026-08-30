<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSite;

/**
 * RBAC des sites opérationnels (FUEL-011, #5805). deny-by-default : CRUD
 * manager, lecture employé du tenant.
 */
class FuelSitePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelSite $site): bool
    {
        return $site->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelSite $site): bool
    {
        return $actor->isManager() && $site->company_id === (string) $actor->company_id;
    }
}
