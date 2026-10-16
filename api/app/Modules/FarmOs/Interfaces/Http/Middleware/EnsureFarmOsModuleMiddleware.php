<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Farm OS (mission Farm OS, BC-37 AGRICULTURE).
 *
 * Exige que la company courante ait le feature flag `farm_os` activé
 * (companies.features.farm_os = true) — pattern calqué sur
 * EnsureVtcModuleMiddleware (BC-35) et EnsureDeliveryModuleMiddleware.
 *
 * Le flag est posé par `SolutionActivator` à l'activation de la solution
 * `farm_os` ; il est FAIL-CLOSED (tenant sans le flag ⇒ 403 immédiat).
 * Désactiver le flag coupe l'accès sans toucher aux données (aucune
 * suppression : la réactivation retrouve l'exploitation intacte).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Le contrôle des CAPACITÉS activées (13 outils) est réalisé par un middleware
 * distinct, plus fin, monté route par route — jamais ici.
 */
class EnsureFarmOsModuleMiddleware
{
    private const FEATURE = 'farm_os';

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

        if (! $company->hasFeature(self::FEATURE)) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Farm OS module.',
            ], 403);
        }

        return $next($request);
    }
}
