<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * #5805 — RBAC équipements FuelStation (pompes, cuves, produits) (FUEL-011).
 *
 * Deny-by-default : seul le manager principal/rh écrit ; lecture limitée au
 * tenant courant.
 */
class FuelEquipmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Model $equipment): bool
    {
        return (string) $equipment->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Model $equipment): bool
    {
        return $actor->isManager() && (string) $equipment->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, Model $equipment): bool
    {
        return $actor->isManager() && (string) $equipment->company_id === (string) $actor->company_id;
    }
}
