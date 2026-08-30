<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;

/**
 * RBAC des shifts FuelStation (FUEL-005, #5799).
 *
 * - Manager (role=manager) : gestion complète des shifts et affectations.
 * - Employé (pompiste) : pas d'accès direct à l'administration — il lit ses
 *   propres affectations via l'endpoint self-service /fuel-station/me/shifts
 *   (scope employee_id dans le contrôleur, aucune fuite tenant possible).
 */
class FuelShiftPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager();
    }

    public function assign(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager();
    }

    public function viewAssignments(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager();
    }

    public function cancelAssignment(Employee $actor, FuelShiftAssignment $assignment): bool
    {
        return $actor->isManager();
    }
}
