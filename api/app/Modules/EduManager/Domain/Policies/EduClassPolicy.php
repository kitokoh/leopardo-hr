<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduClass;

/**
 * #5819 (EDU-003) — Policy des classes.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * classes ; accès borné au tenant (`company_id`). Les permissions fines du
 * manifest (`edu.admin`/`edu.teacher`/`edu.guardian`) seront câblées avec
 * l'API EduManager (EDU-006/EDU-010).
 */
class EduClassPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduClass $class): bool
    {
        // La direction voit toutes les classes de son tenant ; un ENSEIGNANT
        // voit celles qu'il enseigne (référent, affectation ou séance) sans
        // pour autant les administrer — cf. EduAccess.
        return $this->viewAny($actor)
            ? $class->company_id === $actor->company_id
            : EduAccess::canViewClass($actor, $class);
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduClass $class): bool
    {
        // Administration de la classe (renommage, capacité, référent) :
        // réservée à la direction — un enseignant ne se l'octroie pas.
        return $this->viewAny($actor) && $class->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, EduClass $class): bool
    {
        return $this->update($actor, $class);
    }
}
