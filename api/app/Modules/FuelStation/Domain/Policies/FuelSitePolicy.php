<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSite;

/**
 * RBAC des sites opérationnels FuelStation (FUEL-011, #5805).
 * Gestion réservée au manager (deny-by-default pour les pompistes).
 */
class FuelSitePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelSite $site): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelSite $site): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor, FuelSite $site): bool
    {
        return $actor->isManager();
    }
}
