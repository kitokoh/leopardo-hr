<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduClass;

/**
 * RBAC des classes (EDU-009, #5825). deny-by-default : CRUD direction ;
 * lecture pour tout employé du tenant ; l'enseignant voit ses classes.
 */
class EduClassPolicy
{
    use EduSchoolRoles;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, EduClass $class): bool
    {
        return $class->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, EduClass $class): bool
    {
        return $actor->isManager() && $class->company_id === (string) $actor->company_id;
    }
}
