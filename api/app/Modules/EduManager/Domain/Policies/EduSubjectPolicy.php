<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduSubject;

/**
 * #5825 (EDU-009) — matières : direction uniquement pour la gestion ;
 * lecture pour les enseignants.
 */
class EduSubjectPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduSubject;

/**
 * #5819 (EDU-003) — Policy des matières.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent le
 * référentiel des matières ; accès borné au tenant (`company_id`). Les
 * permissions fines du manifest (`edu.admin`/`edu.teacher`/`edu.guardian`)
 * seront câblées avec l'API EduManager (EDU-006/EDU-010).
 */
class EduSubjectPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduSubject $subject): bool
    {
        return $subject->company_id === $actor->company_id && $this->viewAny($actor);
        return $this->viewAny($actor) && $subject->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduSubject $subject): bool
    {
        return $this->view($actor, $subject) && EduAccess::isAdmin($actor);
        return $this->view($actor, $subject);
    }

    public function delete(Employee $actor, EduSubject $subject): bool
    {
        return $this->update($actor, $subject);
        return $this->view($actor, $subject);
    }
}
