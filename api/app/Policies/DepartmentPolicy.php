<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;

class DepartmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Department $department): bool
    {
        return $department->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Department $department): bool
    {
        return $department->company_id === $actor->company_id && $actor->isManager();
    }

    public function delete(Employee $actor, Department $department): bool
    {
        return $department->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
