<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\HR\Domain\Models\Department;
use App\Core\Auth\Domain\Models\Employee;

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

