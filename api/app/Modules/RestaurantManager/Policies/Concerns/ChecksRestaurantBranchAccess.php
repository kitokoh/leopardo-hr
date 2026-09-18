<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies\Concerns;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;

/**
 * Issue #7599 (R2 de l'épique #7597) — autorisation ressource-scopée du
 * pilote Restaurant : la succursale (`restaurant_branch`) devient l'objet
 * d'autorisation, à la place des conditions mortes du motif
 * « hasManagerRole élargi aux valeurs 'manager'/'server' » — valeurs qui ne
 * sont pas assignables via `manager_role` (trou n°2 de l'épique).
 *
 * Niveaux (conception §3.3) : lecture = `view`, POS/commandes/service =
 * `operate`, menus/prix/stocks/rapports/COGS/référentiels = `manage`.
 *
 * Règle de progressivité (issue #7598) : tant qu'AUCUNE assignation
 * `restaurant_branch` n'existe dans l'entreprise, le comportement historique
 * est conservé (lecture = tout employé du tenant, écriture = principal/rh).
 * Dès la première assignation, le scoping est actif et fail-closed : un
 * non-assigné ne lit plus et n'écrit plus.
 */
trait ChecksRestaurantBranchAccess
{
    /**
     * Lecture d'une ressource portée par une succursale. `null` = ressource
     * company-wide (référentiels : fournisseurs, unités, taux de taxe,
     * catégories/produits « toutes branches ») — le périmètre reste alors la
     * vérification `company_id` faite par la policy appelante.
     */
    protected function canViewBranchResource(Employee $actor, mixed $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        if (! is_int($branchId) && ! is_string($branchId)) {
            return false;
        }

        return $actor->hasResourceAccess(
            'restaurant_branch',
            (int) $branchId,
            EmployeeResourceAssignment::LEVEL_VIEW
        );
    }

    /** Geste opérationnel (commandes, POS, service, réservations, livraisons). */
    protected function canOperateBranchResource(Employee $actor, mixed $branchId): bool
    {
        return $this->canWriteBranchResource($actor, $branchId, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /** Geste de gestion (menus, prix, stocks, achats, rapports, COGS, référentiels). */
    protected function canManageBranchResource(Employee $actor, mixed $branchId): bool
    {
        return $this->canWriteBranchResource($actor, $branchId, EmployeeResourceAssignment::LEVEL_MANAGE);
    }

    /**
     * Écriture au niveau `$min`.
     *
     *  - Type non encore assigné dans l'entreprise → comportement historique
     *    (`principal`/`rh`), aucune régression avant la première assignation ;
     *  - succursale connue → `hasResourceAccess` (principal passe toujours,
     *    `rh` retombe à la lecture seule — conception §3.3) ;
     *  - succursale inconnue, exigence `manage` → ressource company-wide
     *    (fournisseurs, taux de taxe, unités, programme de fidélité…) : c'est
     *    de la configuration du tenant, réservée au `principal` dès que le
     *    scoping est actif (un gérant de succursale gère SA succursale, pas
     *    le référentiel commun) ;
     *  - succursale inconnue, exigence `operate` (geste courant dont le
     *    contrôleur ne connaît pas la branche — fidélité, création dérivée
     *    d'un parent) → il faut détenir le niveau sur AU MOINS une
     *    succursale. La ressource précise est ensuite re-vérifiée par
     *    `view`/`update` sur le modèle persistant.
     */
    private function canWriteBranchResource(Employee $actor, mixed $branchId, string $min): bool
    {
        if (! $actor->isResourceTypeScoped('restaurant_branch')) {
            return $actor->hasManagerRole('principal', 'rh');
        }

        if (is_int($branchId) || is_string($branchId)) {
            return $actor->hasResourceAccess('restaurant_branch', (int) $branchId, $min);
        }

        if ($branchId !== null) {
            return false;
        }

        if ($min === EmployeeResourceAssignment::LEVEL_MANAGE) {
            return $actor->isPrincipal();
        }

        $accessible = $actor->accessibleResourceIds('restaurant_branch', $min);

        return $accessible === null || $accessible !== [];
    }
}
