<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStation;

/**
 * RBAC des stations FuelStation (FUEL-011, #5805).
 *
 * deny-by-default : seul un manager peut gérer le référentiel (CRUD
 * stations/sites) ; la consultation est ouverte aux employés du tenant.
 */
class FuelStationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelStation $station): bool
    {
        return $station->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager() && $station->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager() && $station->company_id === (string) $actor->company_id;
    }
}
