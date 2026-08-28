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

    /**
     * Fail-closed cross-tenant (pattern EmployeePolicy::view, #3232) : un
     * manager ne peut modifier un log/correction que de SON tenant, même si
     * les IDs coïncident. DEP-BC05 (#5881) — le check company_id manquait,
     * la liste des corrections fuyait cross-tenant (PII).
     */
    public function update(Employee $actor, AttendanceLog $log): bool
    {
        if ($log->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }
}
