<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduTeacher;

/**
 * #5819 (EDU-003) — Policy des enseignants.
 *
 * Le référentiel des enseignants est géré par les rôles de gestion du tenant
 * (principal, rh, manager) UNIQUEMENT : un enseignant ne gère pas le
 * référentiel (pas de branche « view » pour le profil enseignant lui-même —
 * contrairement à `EduGuardianPolicy` qui autorise le gardien sur SON
 * profil). Accès borné au tenant (`company_id`).
 */
class EduTeacherPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduTeacher $teacher): bool
    {
        return $this->viewAny($actor) && $teacher->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduTeacher $teacher): bool
    {
        return $this->view($actor, $teacher);
    }

    public function delete(Employee $actor, EduTeacher $teacher): bool
    {
        return $this->view($actor, $teacher);
    }
}
