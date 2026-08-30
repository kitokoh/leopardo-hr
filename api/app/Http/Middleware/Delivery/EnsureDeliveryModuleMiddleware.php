<?php

declare(strict_types=1);

namespace App\Http\Middleware\Delivery;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Delivery (DELIVERY-101, issue #6282).
 *
 * Exige que la company courante ait le feature flag `delivery` activé
 * (companies.features.delivery = true) — pattern calqué sur
 * EnsureCameraModuleMiddleware (module.cameras),
 * EnsureTravelAgencyModuleMiddleware (module.travelagency) et
 * EnsureRestaurantManagerModuleMiddleware (module.restaurantmanager).
 *
 * BC-26 DELIVERY est un module de livraison dernier-kilomètre générique :
 * tout tenant qui livre (agence, restaurant, retail, e-commerce, CRM,
 * pharmacie) active le même moteur via ce flag.
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureDeliveryModuleMiddleware
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

        if (! $company->hasFeature('delivery')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Delivery module.',
            ], 403);
        }

        return $next($request);
    }
}
