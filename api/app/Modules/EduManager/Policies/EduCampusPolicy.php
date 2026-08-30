<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
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
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduCampus $campus): bool
    {
        return $this->viewAny($actor) && $campus->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduCampus $campus): bool
    {
        return $this->view($actor, $campus);
    }

    public function delete(Employee $actor, EduCampus $campus): bool
    {
        return $this->view($actor, $campus);
    }
}
