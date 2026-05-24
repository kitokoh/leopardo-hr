<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Absence;
use App\Models\Employee;

class AbsencePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Absence $absence): bool
    {
        if ($absence->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $absence->employee_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function approve(Employee $actor, Absence $absence): bool
    {
        return $absence->company_id === $actor->company_id && $actor->isManager();
    }

    public function reject(Employee $actor, Absence $absence): bool
    {
        return $absence->company_id === $actor->company_id && $actor->isManager();
    }

    public function delete(Employee $actor, Absence $absence): bool
    {
        if ($absence->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->id === $absence->employee_id && $absence->status === 'pending';
    }
}
