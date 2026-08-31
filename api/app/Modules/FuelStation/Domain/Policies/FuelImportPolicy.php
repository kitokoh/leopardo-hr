<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelImport;

/**
 * RBAC des imports FuelStation (FUEL-018, #5812).
 *
 * Manager uniquement (import/export = opération de pilotage sensible) ;
 * deny-by-default pour les employés.
 */
class FuelImportPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelImport $import): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
