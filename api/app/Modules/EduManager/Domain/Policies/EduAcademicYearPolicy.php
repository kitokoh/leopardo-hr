<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;

/**
 * #5819 (EDU-003) — Policy des années scolaires.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * années scolaires ; accès borné au tenant (`company_id`). Les permissions
 * fines du manifest (`edu.admin`/`edu.teacher`/`edu.guardian`) seront
 * câblées avec l'API EduManager (EDU-006/EDU-010).
 */
class EduAcademicYearPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduAcademicYear $academicYear): bool
    {
        return $this->viewAny($actor) && $academicYear->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduAcademicYear $academicYear): bool
    {
        return $this->view($actor, $academicYear);
    }

    public function delete(Employee $actor, EduAcademicYear $academicYear): bool
    {
        return $this->view($actor, $academicYear);
    }
}
