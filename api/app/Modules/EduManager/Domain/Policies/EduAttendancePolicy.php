<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduClass;

/**
 * #5825 (EDU-009) — présence scolaire : la direction voit tout ; un
 * enseignant ne saisit/corrige que pour SES classes.
 */
class EduAttendancePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduAttendance $attendance): bool
    {
        if ($attendance->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        $class = $attendance->class;

        return $class !== null && EduAccess::canViewClass($actor, $class);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    /**
     * Correction de présence : direction, ou enseignant de la classe.
     */
    public function correct(Employee $actor, EduAttendance $attendance): bool
    {
        if ($attendance->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        $class = $attendance->class;

        return $class !== null && EduAccess::canManageClass($actor, $class);
    }

    public function createForClass(Employee $actor, EduClass $class): bool
    {
        return EduAccess::canManageClass($actor, $class);
    }
}
