<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduSubject;

/**
 * #5825 (EDU-009) — matières : direction uniquement pour la gestion ;
 * lecture pour les enseignants.
 */
class EduSubjectPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduSubject $subject): bool
    {
        return $subject->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduSubject $subject): bool
    {
        return $this->view($actor, $subject) && EduAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, EduSubject $subject): bool
    {
        return $this->update($actor, $subject);
    }
}
