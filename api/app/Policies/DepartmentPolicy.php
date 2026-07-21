<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Department;

class DepartmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Department $department): bool
    {
        if ($department->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->isDepartmentScoped()) {
            return $actor->department_id !== null && $actor->department_id === $department->id;
        }

        // A superviseur has no department of their own to scope on; their
        // visibility is defined by directly assigned employees, not by
        // department membership (PA2-SEC-003), so department listings stay
        // company-wide for them like other non-dept manager roles.
        return true;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, Department $department): bool
    {
        if ($department->company_id !== $actor->company_id || ! $actor->isManager()) {
            return false;
        }

        if ($actor->isDepartmentScoped()) {
            return $actor->department_id !== null && $actor->department_id === $department->id;
        }

        return true;
    }

    public function delete(Employee $actor, Department $department): bool
    {
        return $department->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
