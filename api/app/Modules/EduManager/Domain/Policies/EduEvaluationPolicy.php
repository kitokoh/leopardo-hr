<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduEvaluation;
use App\Modules\EduManager\Domain\Models\EduGradeEntry;

/**
 * RBAC des évaluations & notes (EDU-009, #5825). deny-by-default :
 * - direction : tout (créer, publier, corriger) ;
 * - enseignant : gérer les évaluations/notes de SES classes uniquement ;
 * - une note publiée n'est corrigée que par direction (nouvelle version).
 */
class EduEvaluationPolicy
{
    use EduSchoolRoles;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, EduEvaluation $evaluation): bool
    {
        if ($evaluation->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $this->teachesClass($actor, (int) $evaluation->class_id);
    }

    public function create(Employee $actor, int $classId): bool
    {
        return $actor->isManager() || $this->teachesClass($actor, $classId);
    }

    public function publish(Employee $actor, EduEvaluation $evaluation): bool
    {
        if ($evaluation->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $this->teachesClass($actor, (int) $evaluation->class_id);
    }

    public function viewGrade(Employee $actor, EduGradeEntry $entry): bool
    {
        if ($entry->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($actor->isManager()) {
            return true;
        }

        $evaluation = EduEvaluation::query()->find($entry->evaluation_id);

        return $evaluation instanceof EduEvaluation
            && $this->teachesClass($actor, (int) $evaluation->class_id);
    }

    public function correct(Employee $actor, EduGradeEntry $entry): bool
    {
        if ($entry->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($actor->isManager()) {
            return true;
        }

        $evaluation = EduEvaluation::query()->find($entry->evaluation_id);

        return $evaluation instanceof EduEvaluation
            && $this->teachesClass($actor, (int) $evaluation->class_id);
    }
}
