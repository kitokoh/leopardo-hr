<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduClass;

/**
 * #5825 (EDU-009) — classes : gestion par la direction, périmètre
 * enseignant pour la lecture (classes référentes + enseignées).
 */
class EduClassPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduClass $class): bool
    {
        return EduAccess::canViewClass($actor, $class);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduClass $class): bool
    {
        return EduAccess::canManageClass($actor, $class);
    }

    public function delete(Employee $actor, EduClass $class): bool
    {
        return EduAccess::isAdmin($actor) && $class->company_id === $actor->company_id;
    }
}
