<?php

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;

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

    /**
     * PA2-ATT-004 - Every authenticated employee may view anomalies detected
     * on their own attendance logs (late arrivals, missing check-outs, etc.).
     * This is intentionally unrestricted by role: it only ever surfaces the
     * caller's own records, scoped by employee_id in the controller/service.
     */
    public function viewOwnAnomalies(Employee $actor): bool
    {
        return true;
    }

    public function viewForEmployee(Employee $actor, Employee $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($actor->isTeamScoped()) {
            return $actor->managesTeamMemberOf($target);
        }

        return true;
    }

    public function update(Employee $actor, AttendanceLog $log): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
