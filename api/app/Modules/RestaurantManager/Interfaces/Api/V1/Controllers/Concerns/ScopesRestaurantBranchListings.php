<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\Concerns;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Issue #7599 (R2 de l'épique #7597) — scoping des listings Restaurant par
 * succursales accessibles.
 *
 * `accessibleResourceIds('restaurant_branch')` retourne `null` tant que le
 * type n'est pas assigné dans l'entreprise (comportement historique conservé,
 * ou acteur `principal`/lecture `rh`) : dans ce cas, aucun filtre. Dès que le
 * scoping est actif, la liste est restreinte aux succursales assignées — une
 * liste vide d'assignations = un listing vide (fail-closed), jamais un 500.
 *
 * Les lignes `branch_id IS NULL` (catégories/produits « toutes branches »)
 * restent visibles : ce sont des ressources company-wide, pas des données
 * d'une succursale.
 */
trait ScopesRestaurantBranchListings
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scopeToAccessibleBranches(Employee $actor, Builder $query, string $column = 'branch_id'): Builder
    {
        $branchIds = $actor->accessibleResourceIds('restaurant_branch');

        if ($branchIds === null) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($column, $branchIds): void {
            $scoped->whereIn($column, $branchIds)->orWhereNull($column);
        });
    }
}
