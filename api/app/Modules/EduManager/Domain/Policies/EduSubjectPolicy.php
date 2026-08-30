<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC des matières (EDU-009, #5825). deny-by-default : CRUD direction,
 * lecture pour tout employé du tenant.
 */
class EduSubjectPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
