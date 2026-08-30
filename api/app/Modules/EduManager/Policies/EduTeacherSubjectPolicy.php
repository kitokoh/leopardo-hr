<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;

/**
 * #5825 (EDU-009) — affectations enseignant→matière : direction pour la
 * gestion, enseignant pour ses propres affectations.
 */
class EduTeacherSubjectPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduTeacherSubject $assignment): bool
    {
        if ($assignment->company_id !== $actor->company_id) {
            return false;
        }

        return EduAccess::isAdmin($actor) || $assignment->teacher_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduTeacherSubject $assignment): bool
    {
        return $this->view($actor, $assignment) && EduAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, EduTeacherSubject $assignment): bool
    {
        return $this->update($actor, $assignment);
    }
}
