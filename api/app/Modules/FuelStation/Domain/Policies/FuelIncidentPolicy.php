<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;

/**
 * RBAC des incidents FuelStation (FUEL-010, #5804). deny-by-default.
 *
 * - create : tout employé authentifié (pompiste) signale un incident ;
 * - view : manager, signalé par l'employé, ou assigné ;
 * - assign/resolve/close : manager uniquement (permissions par site via la
 *   FK composite (station_id, company_id) — cross-tenant impossible).
 */
class FuelIncidentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        if ($incident->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager()
            || $incident->reported_by === $actor->id
            || $incident->assigned_to === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function assign(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() && $incident->company_id === (string) $actor->company_id;
    }

    public function resolve(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() && $incident->company_id === (string) $actor->company_id;
    }

    public function close(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() && $incident->company_id === (string) $actor->company_id;
    }
}
