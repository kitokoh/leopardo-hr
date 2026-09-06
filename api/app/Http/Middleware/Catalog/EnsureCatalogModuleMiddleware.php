<?php

declare(strict_types=1);

namespace App\Http\Middleware\Catalog;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Catalog B2B (BC-28 CATALOG, #6881).
 *
 * Exige que la company courante ait le feature flag `b2b_catalog` activé
 * (companies.features.b2b_catalog = true, mécanisme Core/Feature) — pattern
 * calqué sur EnsureTravelAgencyModuleMiddleware (TRAVEL-102, #6007).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureCatalogModuleMiddleware
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

        if (! $company->hasFeature('b2b_catalog')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the B2B Catalog module.',
            ], 403);
        }

        return $next($request);
    }
}
