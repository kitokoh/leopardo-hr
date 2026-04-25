<?php

namespace App\Http\Middleware\Cameras;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de gate sur le module Surveillance Caméras (APV L.08).
 *
 * Exige que la company ait le feature flag `cameras` activé
 * (companies.features.cameras = true) et que le plan tarifaire soit adapté
 * (Business / Enterprise).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 */
class EnsureCameraModuleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = app()->bound('current_company') ? app('current_company') : null;

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'COMPANY_NOT_FOUND',
            ], 403);
        }

        if (! method_exists($company, 'hasFeature') || ! $company->hasFeature('cameras')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the cameras module. Upgrade to Business.',
            ], 403);
        }

        return $next($request);
    }
}
