<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Issue #6562 — borne un parametre ?limit= (defaut, plafond) pour les
     * listes non paginees : reponse bornee, pas de dump complet (DoS/lenteur).
     * Additif : la forme de la reponse ne change pas, le client peut monter
     * jusqu'au plafond.
     */
    protected function boundedLimit(Request $request, int $default, int $max): int
    {
        $limit = $request->integer('limit', $default);

        return max(1, min($limit, $max));
    }
}
