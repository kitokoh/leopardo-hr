<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAssessment;

/**
 * #5825 (EDU-009) — évaluations : la direction gère tout ; un enseignant
 * crée/modifie les évaluations de SES classes et les consulte.
 */
class EduAssessmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduAssessment $assessment): bool
    {
        if ($assessment->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        $class = $assessment->class;

        return $class !== null && EduAccess::canViewClass($actor, $class);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function update(Employee $actor, EduAssessment $assessment): bool
    {
        if ($assessment->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        $class = $assessment->class;

        return $class !== null && EduAccess::canManageClass($actor, $class);
    }

    public function delete(Employee $actor, EduAssessment $assessment): bool
    {
        return $this->update($actor, $assessment);
    }
}
