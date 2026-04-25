<?php

namespace App\Policies;

use App\Models\Employee;

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
        return $actor->isManager() || $actor->id === $target->id;
    }
}
