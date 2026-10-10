<?php

declare(strict_types=1);

namespace App\Http\Middleware\Geo;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Geo (GEO-02, issue #8351, BC-33 GEO).
 *
 * Exige que la company courante ait le feature flag `geo` activé
 * (companies.features.geo = true) — pattern calqué sur
 * EnsureDeliveryModuleMiddleware (module.delivery, DELIVERY-101/#6282).
 *
 * BC-33 GEO est le core géospatial transverse : tout tenant dont une
 * verticale exige du positionnement (VTC, pharmacie la plus proche,
 * restaurant, livraison) active le même moteur via ce flag.
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureGeoModuleMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
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

        if (! $company->hasFeature('geo')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Geo module.',
            ], 403);
        }

        return $next($request);
    }
}
