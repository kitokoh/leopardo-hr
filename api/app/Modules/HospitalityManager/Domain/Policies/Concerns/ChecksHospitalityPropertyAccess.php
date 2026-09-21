<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies\Concerns;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;

/**
 * HOSP-003 (#7945, BC-32) — autorisation ressource-scopée par établissement
 * (`hospitality_property`), copie du pattern restaurant #7598/#7599.
 *
 * Niveaux (spec §4) : lecture = `view`, gestion (référentiel, inventaire,
 * équipe, publication, réservations, baux) = `manage` ; `operate` est réservé
 * aux gestes opérationnels de guichet (HOSP-004 : transitions réservations).
 *
 * Règle de progressivité (spec §4) : tant qu'AUCUNE assignation
 * `hospitality_property` n'existe dans l'entreprise, le comportement
 * historique est conservé (fallback `hasManagerRole('principal','rh')` =
 * `HospitalityAccess::isAdmin`). Dès la première assignation, le scoping est
 * actif et fail-closed via `Employee::hasResourceAccess()` : un non-assigné
 * ne lit plus et n'écrit plus (principal passe toujours, rh retombe à la
 * lecture seule — conception restaurant §3.3).
 */
trait ChecksHospitalityPropertyAccess
{
    /** Niveaux d'autorisation de la ressource `hospitality_property`. */
    private function canViewPropertyResource(Employee $actor, mixed $propertyId): bool
    {
        if (! $actor->isResourceTypeScoped('hospitality_property')) {
            // Aucune assignation dans le tenant → direction historique.
            return $actor->hasManagerRole('principal', 'rh');
        }

        if (is_int($propertyId) || is_string($propertyId)) {
            return $actor->hasResourceAccess(
                'hospitality_property',
                (int) $propertyId,
                EmployeeResourceAssignment::LEVEL_VIEW
            );
        }

        // Ressource company-wide inconnue : principal (tout) ou rh (lecture)
        // passent toujours ; sinon il faut AU MOINS une propriété lisible.
        if ($propertyId !== null) {
            return false;
        }

        $accessible = $actor->accessibleResourceIds(
            'hospitality_property',
            EmployeeResourceAssignment::LEVEL_VIEW
        );

        return $accessible === null || $accessible !== [];
    }

    /** Geste opérationnel de guichet (transitions de réservations — HOSP-004). */
    private function canOperatePropertyResource(Employee $actor, mixed $propertyId): bool
    {
        return $this->canWritePropertyResource($actor, $propertyId, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /** Geste de gestion (référentiel, inventaire, équipe, publication, baux). */
    private function canManagePropertyResource(Employee $actor, mixed $propertyId): bool
    {
        return $this->canWritePropertyResource($actor, $propertyId, EmployeeResourceAssignment::LEVEL_MANAGE);
    }

    /**
     * Écriture au niveau `$min`.
     *
     *  - Type non encore assigné dans l'entreprise → comportement historique
     *    (`principal`/`rh`), aucune régression avant la première assignation ;
     *  - établissement connu → `hasResourceAccess` (principal passe toujours,
     *    `rh` retombe à la lecture seule) ;
     *  - établissement inconnu, exigence `manage` → ressource company-wide
     *    (ex. création d'un établissement) : réservée au `principal` dès que
     *    le scoping est actif (un responsable de site gère SON site, pas le
     *    référentiel commun) ;
     *  - établissement inconnu, exigence `operate` → il faut détenir le
     *    niveau sur AU MOINS un établissement (la ressource précise est
     *    re-vérifiée par la policy du modèle persistant).
     */
    private function canWritePropertyResource(Employee $actor, mixed $propertyId, string $min): bool
    {
        if (! $actor->isResourceTypeScoped('hospitality_property')) {
            return $actor->hasManagerRole('principal', 'rh');
        }

        if (is_int($propertyId) || is_string($propertyId)) {
            return $actor->hasResourceAccess('hospitality_property', (int) $propertyId, $min);
        }

        if ($propertyId !== null) {
            return false;
        }

        if ($min === EmployeeResourceAssignment::LEVEL_MANAGE) {
            return $actor->isPrincipal();
        }

        $accessible = $actor->accessibleResourceIds('hospitality_property', $min);

        return $accessible === null || $accessible !== [];
    }
}
