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
        if ($actor->id === $employee->id) {
            return true;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }

    public function archive(Employee $actor, Employee $employee): bool
    {
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
