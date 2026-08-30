<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC du reporting opérationnel (FUEL-017, #5811). deny-by-default :
 * rapports et exports réservés aux managers.
 */
class FuelReportPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function export(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
