<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;

/**
 * #5825 (EDU-009) — années scolaires : direction uniquement (création,
 * clôture). Les enseignants consultent via leurs classes.
 */
class EduAcademicYearPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduAcademicYear $year): bool
    {
        return $year->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduAcademicYear $year): bool
    {
        return $this->view($actor, $year) && EduAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, EduAcademicYear $year): bool
    {
        return $this->update($actor, $year);
    }
}
