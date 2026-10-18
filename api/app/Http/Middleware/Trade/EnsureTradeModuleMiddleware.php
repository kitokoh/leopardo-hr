<?php

declare(strict_types=1);

namespace App\Http\Middleware\Trade;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Trade (TRADE-001, issue #8476, BC-36 TRADE).
 *
 * Exige que la company courante ait le feature flag `trade` activé
 * (companies.features.trade = true) — pattern calqué sur
 * EnsureVtcModuleMiddleware (module.vtc, VTC-01/#8357) et
 * EnsureDeliveryModuleMiddleware (module.delivery, DELIVERY-101/#6282).
 *
 * BC-36 TRADE est la verticale International Trade OS : commerce
 * international & B2B domestique (sourcing, RFQ, offres, commandes,
 * transport, coûts rendus, documents). Le flag est posé par
 * `SolutionActivator` à l'activation de la solution `trade`.
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat, sans toucher
 * aux données.
 */
class EnsureTradeModuleMiddleware
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

        if (! $company->hasFeature('trade')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Trade module.',
            ], 403);
        }

        return $next($request);
    }
}
