<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduClass;

/**
 * RBAC de la présence scolaire (EDU-009, #5825). deny-by-default :
 * - direction : tout voir/enregistrer ;
 * - enseignant : enregistrer/corriger sur SES classes uniquement ;
 * - lecture liste restreinte aux classes enseignées pour un enseignant.
 */
class EduAttendancePolicy
{
    use EduSchoolRoles;

    public function viewAny(Employee $actor): bool
    {
        return true; // filtré par classe dans le contrôleur (scope tenant)
    }

    public function view(Employee $actor, EduAttendanceRecord $record): bool
    {
        if ($record->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $this->teachesClass($actor, (int) $record->class_id);
    }

    public function create(Employee $actor, EduClass $class): bool
    {
        if ($class->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $this->teachesClass($actor, (int) $class->id);
    }

    public function correct(Employee $actor, EduAttendanceRecord $record): bool
    {
        if ($record->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $this->teachesClass($actor, (int) $record->class_id);
    }
}
