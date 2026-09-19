<?php

declare(strict_types=1);

namespace App\Http\Middleware\Retail;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Retail (BC-17 RETAIL, #7672).
 *
 * Exige que la company courante ait le feature flag `retail` activé
 * (companies.features.retail = true, mécanisme Core/Feature) — pattern
 * calqué sur EnsureCatalogModuleMiddleware (BC-28, #6881).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureRetailModuleMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = app()->bound('current_company') ? currentCompany() : null;

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'COMPANY_NOT_FOUND',
            ], 403);
        }

        if (! $company->hasFeature('retail')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Retail module.',
            ], 403);
        }

        return $next($request);
    }
}
