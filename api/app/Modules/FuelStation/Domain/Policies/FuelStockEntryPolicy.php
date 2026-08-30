<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC du stock FuelStation (FUEL-009, #5803). deny-by-default : seuls les
 * managers enregistrent les entrées de stock et consultent le rapprochement.
 */
class FuelStockEntryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
