<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStation;

/**
 * #5805 — RBAC stations FuelStation (FUEL-011) : deny-by-default.
 *
 * Manager principal/rh : CRUD complet. Opérateur (tout employé du tenant) :
 * lecture seule du référentiel de sa société.
 */
class FuelStationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true; // tout employé authentifié du tenant peut lister les stations
    }

    public function view(Employee $actor, FuelStation $station): bool
    {
        return $station->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager() && $station->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager() && $station->company_id === $actor->company_id;
    }
}
