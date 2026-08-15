<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restaure le search_path PostgreSQL après une requête kiosque.
 *
 * Les handlers kiosque basculent le search_path vers le schéma du tenant
 * (setTenantSearchPath) sans try/finally. Sur les connexions persistantes
 * (workers de queue, pool), la requête suivante hériterait du schéma du
 * tenant précédent — risque de résolution cross-tenant (#3368, même pattern
 * que resolveAuthorizedKiosk / issue #2689).
 */
final class EnsureKioskSearchPathReset
{
    public function handle(Request $request, Closure $next): Response
    {
        $searchPathRow = DB::selectOne('SHOW search_path');
        $previous = (string) ($searchPathRow->search_path ?? 'shared_tenants,public');

        try {
            return $next($request);
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }
}
