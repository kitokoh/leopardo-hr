<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * RBAC des cuves FuelStation (FUEL-011, #5805).
 * Gestion réservée au manager (deny-by-default pour les pompistes).
 */
class FuelTankPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelTank $tank): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelTank $tank): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor, FuelTank $tank): bool
    {
        return $actor->isManager();
    }
}
