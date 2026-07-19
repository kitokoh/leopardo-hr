<?php

namespace App\Policies;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Auth\Domain\Models\Employee;

class AttendancePolicy
{
    public function checkIn(Employee $actor): bool
    {
        return $actor->status === 'active' && $actor->role === 'employee';
    }

    public function checkOut(Employee $actor): bool
    {
        return $actor->status === 'active' && $actor->role === 'employee';
    }

    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewForEmployee(Employee $actor, Employee $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($actor->isDepartmentScoped()) {
            return $actor->managesDepartmentOf($target);
        }

        return true;
    }

    public function update(Employee $actor, AttendanceLog $log): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}

