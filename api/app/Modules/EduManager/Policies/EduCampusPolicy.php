<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\EduManager\Domain\Models\EduCampus;

/**
 * #5818 (EDU-002) — Policy des campus scolaires.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * campus. Les permissions fines du manifest (`edu.admin`/`edu.teacher`/
 * `edu.guardian`) seront câblées avec l'API EduManager (EDU-006/EDU-010).
 */
class EduCampusPolicy
{
    use ChecksResourceScopedAccess;

    // #7600 — 'manager' retiré : valeur morte hors enum assignable de
    // manager_role (le pilotage d'un campus s'exprime par une assignation
    // `edu_campus`, épique #7597).
    public const MANAGER_ROLES = ['principal', 'rh'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES) || $actor->isResourceTypeScoped('edu_campus');
    }

    public function view(Employee $actor, EduCampus $campus): bool
    {
        return $campus->company_id === $actor->company_id
            && $this->canViewScopedResource($actor, 'edu_campus', $campus->id, $actor->hasManagerRole(...self::MANAGER_ROLES));
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'edu_campus', null, $actor->hasManagerRole(...self::MANAGER_ROLES));
    }

    public function update(Employee $actor, EduCampus $campus): bool
    {
        return $campus->company_id === $actor->company_id
            && $this->canManageScopedResource($actor, 'edu_campus', $campus->id, $actor->hasManagerRole(...self::MANAGER_ROLES));
    }

    public function delete(Employee $actor, EduCampus $campus): bool
    {
        return $this->update($actor, $campus);
    }
}
