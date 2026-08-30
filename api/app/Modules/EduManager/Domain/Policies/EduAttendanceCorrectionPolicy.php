<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendanceCorrection;

/**
 * Issue #5821 (EDU-005) — Policy des corrections de présence.
 *
 * Le journal de corrections (versionnage) est réservé aux gestionnaires du
 * tenant (`role === 'manager'` ou `manager_role` principal/rh) et borné au
 * tenant : une correction d'un autre tenant est invisible et non modifiable.
 */
class EduAttendanceCorrectionPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isManager($actor);
    }

    public function view(Employee $actor, EduAttendanceCorrection $correction): bool
    {
        return $this->isManager($actor) && $correction->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isManager($actor);
    }

    private function isManager(Employee $actor): bool
    {
        return $actor->role === 'manager' || $actor->hasManagerRole(...self::MANAGER_ROLES);
    }
}
