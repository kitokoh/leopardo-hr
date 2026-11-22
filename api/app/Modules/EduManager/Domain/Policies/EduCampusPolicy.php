<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\EduManager\Domain\Models\EduCampus;

/**
 * #5818 (EDU-002) — Policy des campus scolaires.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * campus. Les permissions fines du manifest (`edu.admin`/`edu.teacher`/
 * `edu.guardian`) seront câblées avec l'API EduManager (EDU-006/EDU-010).
 *
 * #9812 — `edu_campus` est un type de ressource declare dans
 * `config/resource_types.php`, et `docs/security/RBAC_SYSTEM.md` annoncait
 * cette policy comme utilisant le concern. Elle ne l'utilisait pas : tout
 * manager voyait TOUS les campus de l'entreprise, sans egard aux
 * assignations. Corrige ici — lecture au niveau `view`, gestion au niveau
 * `manage`, comportement historique conserve tant qu'aucune assignation
 * n'existe (progressivite #7598).
 */
class EduCampusPolicy
{
    use ChecksResourceScopedAccess;

    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    /** Cle du registre `config/resource_types.php`. */
    public const RESOURCE_TYPE = 'edu_campus';

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduCampus $campus): bool
    {
        return $this->canViewScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $campus->id,
            $this->legacyView($actor, $campus),
        );
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduCampus $campus): bool
    {
        return $this->canManageScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $campus->id,
            $this->legacyView($actor, $campus),
        );
    }

    public function delete(Employee $actor, EduCampus $campus): bool
    {
        return $this->canManageScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $campus->id,
            $this->legacyView($actor, $campus),
        );
    }

    /**
     * Comportement d'avant #9812, applique tant que le type n'est pas scope.
     */
    private function legacyView(Employee $actor, EduCampus $campus): bool
    {
        return $this->viewAny($actor) && $campus->company_id === $actor->company_id;
    }
}
