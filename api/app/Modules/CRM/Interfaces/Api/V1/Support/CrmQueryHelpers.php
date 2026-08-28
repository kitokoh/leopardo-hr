<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5711 — Helpers de liste des controllers CRM client.
 *
 * **Entrées strictement contrôlées** : pagination bornée (1..100), tri
 * allowlisté (sort_by → colonne réelle), direction asc|desc, filtres
 * allowlistés — tout le reste est rejeté en 422. Aucune valeur client
 * n'atteint directement le SQL (aucune injection de tri/filtre).
 */
trait CrmQueryHelpers
{
    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }

    /**
     * @param  array<string, string>  $sortMap  clé publique → colonne réelle
     */
    protected function applySort(Builder $query, Request $request, array $sortMap): Builder
    {
        $sortBy = (string) $request->input('sort_by', 'created_at');

        if (! array_key_exists($sortBy, $sortMap)) {
            throw ValidationException::withMessages([
                'sort_by' => ['Tri non autorisé.'],
            ]);
        }

        $direction = strtolower((string) $request->input('sort_dir', 'desc'));

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages([
                'sort_dir' => ['Direction de tri non autorisée.'],
            ]);
        }

        return $query->orderBy($sortMap[$sortBy], $direction);
    }

    /**
     * @param  list<string>  $allowedQueryKeys  clés de query acceptées (filtres + pagination + tri)
     */
    protected function rejectUnknownQueryKeys(Request $request, array $allowedQueryKeys): void
    {
        $unknown = array_values(array_diff(array_keys($request->query()), $allowedQueryKeys));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filters' => ['Paramètres non autorisés : '.implode(', ', $unknown)],
            ]);
        }
    }
}
