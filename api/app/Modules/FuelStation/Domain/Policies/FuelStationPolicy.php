<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;

/**
 * RBAC des stations & sites FuelStation (FUEL-011, #5805).
 *
 * - Manager : gestion complète du référentiel stations/sites.
 * - Employé : deny-by-default (lecture via les endpoints opérationnels
 *   /fuel-station/me/* et le flux de relevés, jamais le référentiel).
 */
class FuelStationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStation|FuelSite $resource): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelStation|FuelSite $resource): bool
    {
        return $actor->isManager();
    }
}
