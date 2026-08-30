<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStation;

/**
 * RBAC des stations FuelStation (FUEL-011, #5805).
 *
 * - Manager (role=manager) : gestion complète des stations.
 * - Employé (pompiste) : lecture seule de la station de son site via les
 *   endpoints self-service — aucun accès à l'administration (deny-by-default).
 */
class FuelStationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor, FuelStation $station): bool
    {
        return $actor->isManager();
    }
}
