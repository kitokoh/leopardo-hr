<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;

/**
 * #5825 (EDU-009) — affectations enseignant→matière : direction pour la
 * gestion, enseignant pour ses propres affectations.
 */
class EduTeacherSubjectPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;

/**
 * #5819 (EDU-003) — Policy des affectations enseignant → matière.
 *
 * Réservée à la gestion du tenant (principal/rh/manager) ; bornée au tenant
 * (l'affectation contient `company_id` — jamais cross-tenant, et les FK
 * composites rendent une affectation croisée STRUCTURELLEMENT impossible).
 */
class EduTeacherSubjectPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduTeacherSubject $assignment): bool
    {
        if ($assignment->company_id !== $actor->company_id) {
            return false;
        }

        return EduAccess::isAdmin($actor) || $assignment->teacher_id === $actor->id;
        return $this->viewAny($actor) && $assignment->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduTeacherSubject $assignment): bool
    {
        return $this->view($actor, $assignment) && EduAccess::isAdmin($actor);
        return $this->view($actor, $assignment);
    }

    public function delete(Employee $actor, EduTeacherSubject $assignment): bool
    {
        return $this->update($actor, $assignment);
        return $this->view($actor, $assignment);
    }
}
