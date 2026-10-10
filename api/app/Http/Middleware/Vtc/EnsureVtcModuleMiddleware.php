<?php

declare(strict_types=1);

namespace App\Http\Middleware\Vtc;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Vtc (VTC-01, issue #8357, BC-34 VTC).
 *
 * Exige que la company courante ait le feature flag `vtc` activé
 * (companies.features.vtc = true) — pattern calqué sur
 * EnsureDeliveryModuleMiddleware (module.delivery, DELIVERY-101/#6282) et
 * EnsureGeoModuleMiddleware (module.geo, GEO-02/#8351).
 *
 * BC-34 VTC est la verticale VTC/taxi : réservation de courses, dispatch au
 * chauffeur le plus proche (core `geo`), tarification, suivi. Le flag est
 * posé par `SolutionActivator` à l'activation de la solution `vtc`.
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureVtcModuleMiddleware
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

        if (! $company->hasFeature('vtc')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the VTC module.',
            ], 403);
        }

        return $next($request);
    }
}
