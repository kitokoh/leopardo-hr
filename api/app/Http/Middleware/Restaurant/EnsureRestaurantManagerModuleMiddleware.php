<?php

declare(strict_types=1);

namespace App\Http\Middleware\Restaurant;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de la verticale RestaurantManager (RESTO-102, issue #6159).
 *
 * Exige que la company courante ait le feature flag `restaurantmanager`
 * activé (companies.features.restaurantmanager = true) — pattern calqué sur
 * EnsureCameraModuleMiddleware (module.cameras) et
 * EnsureTravelAgencyModuleMiddleware (module.travelagency).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureRestaurantManagerModuleMiddleware
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

        if (! $company->hasFeature('restaurantmanager')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the RestaurantManager module.',
            ], 403);
        }

        return $next($request);
    }
}
