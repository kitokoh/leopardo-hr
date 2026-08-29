<?php

declare(strict_types=1);

namespace App\Http\Middleware\Travel;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de la verticale TravelAgency (TRAVEL-102, issue #6007).
 *
 * Exige que la company courante ait le feature flag `travelagency` activé
 * (companies.features.travelagency = true) — pattern calqué sur
 * EnsureCameraModuleMiddleware (module.cameras).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureTravelAgencyModuleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = app()->bound('current_company') ? currentCompany() : null;

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'COMPANY_NOT_FOUND',
            ], 403);
        }

        if (! method_exists($company, 'hasFeature') || ! $company->hasFeature('travelagency')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the TravelAgency module.',
            ], 403);
        }

        return $next($request);
    }
}
