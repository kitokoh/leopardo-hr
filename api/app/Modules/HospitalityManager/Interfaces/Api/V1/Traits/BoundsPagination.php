<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Traits;

use Illuminate\Http\Request;

/**
 * Bornage de la pagination des listes HospitalityManager — #8019.
 *
 * `(int) $request->input('per_page')` sans borne laissait un client (ou un
 * bot) demander `per_page=1000000000` : PostgreSQL renvoyait tout le
 * référentiel du tenant et la réponse était sérialisée en mémoire (DoS
 * applicatif). Politique unique : 1..1000, défaut fourni par l'appelant.
 */
trait BoundsPagination
{
    /**
     * Taille de page bornée (1..1000).
     */
    protected function boundedPerPage(Request $request, int $default): int
    {
        return max(1, min(1000, (int) $request->query('per_page', $default)));
    }
}
