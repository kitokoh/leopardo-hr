<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduGrade;

/**
 * #5825 (EDU-009) — notes : confidentialité scolaire stricte — la direction
 * voit tout ; un enseignant ne voit/note que les élèves de SES classes.
 */
class EduGradePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduGrade $grade): bool
    {
        if ($grade->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        $assessment = $grade->assessment;
        $class = $assessment?->class;

        return $class !== null && EduAccess::canViewClass($actor, $class);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function update(Employee $actor, EduGrade $grade): bool
    {
        return $this->view($actor, $grade);
    }

    public function correct(Employee $actor, EduGrade $grade): bool
    {
        return $this->view($actor, $grade);
    }
}
