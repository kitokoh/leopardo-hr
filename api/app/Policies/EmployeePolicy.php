<?php

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;

class EmployeePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Employee $employee): bool
    {
        // #3232 : fail-closed cross-tenant — un acteur ne voit jamais un
        // employé d'une autre société, même si les IDs coïncident.
        if ($employee->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->id === $employee->id) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($actor->isTeamScoped()) {
            return $actor->managesTeamMemberOf($employee);
        }

        return true;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, Employee $employee): bool
    {
        // #3232 : un manager ne peut jamais modifier un employé d'un autre tenant.
        if ($employee->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->id === $employee->id) {
            return true;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }

    public function archive(Employee $actor, Employee $employee): bool
    {
        // #3232 : l'archivage est strictement intra-tenant.
        if ($employee->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->id === $employee->id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * Issue #5324 — enregistrement d'un départ (offboarding).
     * Mêmes règles que l'archivage : intra-tenant, jamais soi-même,
     * principal/rh uniquement.
     */
    public function departure(Employee $actor, Employee $employee): bool
    {
        if ($employee->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->id === $employee->id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }

    public function manageInvitations(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
