<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;

/**
 * RBAC des années scolaires (EDU-009, #5825). deny-by-default : CRUD
 * direction, lecture pour tout employé du tenant.
 */
class EduAcademicYearPolicy
{
    use EduSchoolRoles;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, EduAcademicYear $year): bool
    {
        return $year->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, EduAcademicYear $year): bool
    {
        return $actor->isManager() && $year->company_id === (string) $actor->company_id;
    }
}
