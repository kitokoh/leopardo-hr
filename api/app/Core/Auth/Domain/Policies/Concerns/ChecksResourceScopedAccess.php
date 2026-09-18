<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Policies\Concerns;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;

/**
 * Issue #7600 (R3 de l'épique #7597) — généralisation du patron
 * ressource-scopé du pilote Restaurant (#7599) aux autres verticales
 * (TravelAgency, Fleet, FuelStation, EduManager, Cameras…).
 *
 * Le trait vit dans Core (jamais de `Core → Modules`, garde #5584) : chaque
 * policy de module l'importe et passe la clé du registre
 * `config/resource_types.php` (`travel_office`, `vehicle`, `fuel_station`,
 * `edu_campus`, `camera`, …).
 *
 * Sémantique identique au pilote (progressivité, issue #7598) :
 *  - type non encore assigné dans l'entreprise → comportement HISTORIQUE,
 *    fourni par l'appelant (`$legacy`) — aucune régression avant la première
 *    assignation ;
 *  - ressource connue → `hasResourceAccess` (principal passe toujours, `rh`
 *    lecture seule) ;
 *  - ressource inconnue (`create` sans identifiant) : `manage` → `principal`
 *    seul ; sinon le niveau doit être détenu sur AU MOINS une ressource du
 *    type.
 */
trait ChecksResourceScopedAccess
{
    /** Lecture d'une ressource du type. `null` = ressource company-wide. */
    protected function canViewScopedResource(Employee $actor, string $resourceType, mixed $resourceId, bool $legacy = true): bool
    {
        if (! $actor->isResourceTypeScoped($resourceType)) {
            return $legacy;
        }

        if ($resourceId === null) {
            return $legacy;
        }

        if (! is_int($resourceId) && ! is_string($resourceId)) {
            return false;
        }

        return $actor->hasResourceAccess($resourceType, (int) $resourceId, EmployeeResourceAssignment::LEVEL_VIEW);
    }

    /** Geste opérationnel (vente guichet, relevé, correction du quotidien). */
    protected function canOperateScopedResource(Employee $actor, string $resourceType, mixed $resourceId, bool $legacy): bool
    {
        return $this->canWriteScopedResource($actor, $resourceType, $resourceId, EmployeeResourceAssignment::LEVEL_OPERATE, $legacy);
    }

    /** Geste de gestion (référentiels, configuration, rapports). */
    protected function canManageScopedResource(Employee $actor, string $resourceType, mixed $resourceId, bool $legacy): bool
    {
        return $this->canWriteScopedResource($actor, $resourceType, $resourceId, EmployeeResourceAssignment::LEVEL_MANAGE, $legacy);
    }

    private function canWriteScopedResource(Employee $actor, string $resourceType, mixed $resourceId, string $min, bool $legacy): bool
    {
        if (! $actor->isResourceTypeScoped($resourceType)) {
            return $legacy;
        }

        if (is_int($resourceId) || is_string($resourceId)) {
            return $actor->hasResourceAccess($resourceType, (int) $resourceId, $min);
        }

        if ($resourceId !== null) {
            return false;
        }

        if ($min === EmployeeResourceAssignment::LEVEL_MANAGE) {
            return $actor->isPrincipal();
        }

        $accessible = $actor->accessibleResourceIds($resourceType, $min);

        return $accessible === null || $accessible !== [];
    }
}
