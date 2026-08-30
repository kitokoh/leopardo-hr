<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;

/**
 * #5804/#5805 — RBAC incidents FuelStation (FUEL-010/FUEL-011) : deny-by-default.
 *
 * - Opérateur : signaler un incident (create), voir ses incidents assignés.
 * - Manager principal/rh : tout (assignation, transitions, résolution).
 */
class FuelIncidentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        if ((string) $incident->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $incident->assigned_to === $actor->id || $incident->reported_by === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function assign(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() && (string) $incident->company_id === (string) $actor->company_id;
    }

    public function transition(Employee $actor, FuelIncident $incident): bool
    {
        if ((string) $incident->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $incident->assigned_to === $actor->id;
    }
}
