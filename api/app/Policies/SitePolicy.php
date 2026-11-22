<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Core\Tenant\Domain\Models\Site;

/**
 * #9812 — `site` est un type de ressource declare dans
 * `config/resource_types.php`, mais cette policy ne verifiait que
 * `company_id` + `isManager()` : tout manager voyait et modifiait TOUS les
 * sites de l'entreprise, sans egard aux assignations.
 *
 * Corrige ici — lecture au niveau `view`, gestion au niveau `manage`,
 * comportement historique conserve tant qu'aucune assignation n'existe
 * (progressivite #7598).
 */
class SitePolicy
{
    use ChecksResourceScopedAccess;

    /** Cle du registre `config/resource_types.php`. */
    public const RESOURCE_TYPE = 'site';

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Site $site): bool
    {
        return $this->canViewScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $site->id,
            $this->legacyView($actor, $site),
        );
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Site $site): bool
    {
        return $this->canManageScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $site->id,
            $this->legacyView($actor, $site) && $actor->isManager(),
        );
    }

    public function delete(Employee $actor, Site $site): bool
    {
        return $this->canManageScopedResource(
            $actor,
            self::RESOURCE_TYPE,
            $site->id,
            $this->legacyView($actor, $site) && $actor->hasManagerRole('principal'),
        );
    }

    /**
     * Comportement d'avant #9812, applique tant que le type n'est pas scope.
     */
    private function legacyView(Employee $actor, Site $site): bool
    {
        return $site->company_id === $actor->company_id;
    }
}
