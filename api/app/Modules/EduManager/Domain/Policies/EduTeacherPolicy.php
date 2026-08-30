<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduTeacher;

/**
 * RBAC des enseignants & affectations (EDU-009, #5825). deny-by-default :
 * CRUD direction ; lecture pour tout employé du tenant.
 */
class EduTeacherPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, EduTeacher $teacher): bool
    {
        return $teacher->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, EduTeacher $teacher): bool
    {
        return $actor->isManager() && $teacher->company_id === (string) $actor->company_id;
    }
}
