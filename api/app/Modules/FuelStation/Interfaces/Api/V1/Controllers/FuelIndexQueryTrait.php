<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * #5805 — Tri/filtres allowlist + pagination bornée pour les index FuelStation
 * (FUEL-011) : aucun champ SQL arbitraire n'est accepté.
 */
trait FuelIndexQueryTrait
{
    /**
     * Applique tri (allowlist), filtres exacts (allowlist) et pagination
     * bornée (1..100) à une requête FuelStation tenant-scoped.
     *
     * @param  list<string>  $sortable
     * @param  list<string>  $filterable
     */
    private function applyFuelIndexQuery(
        Builder $query,
        Request $request,
        array $sortable,
        array $filterable,
        int $maxPerPage = 100,
    ): LengthAwarePaginator {
        $sortBy = $request->query('sort_by');

        if (is_string($sortBy) && in_array($sortBy, $sortable, true)) {
            $direction = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $direction);
        }

        foreach ($filterable as $field) {
            $value = $request->query($field);

            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), $maxPerPage);

        return $query->paginate($perPage);
    }
}
