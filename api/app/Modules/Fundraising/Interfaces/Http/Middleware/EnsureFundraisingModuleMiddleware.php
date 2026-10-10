<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Http\Middleware;

use App\Modules\Fundraising\Domain\Support\FundraisingFeatures;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de la verticale FUNDRAISING (cagnottes solidaires).
 *
 * Exige que la company courante ait le feature flag `fundraising` activé
 * (companies.features.fundraising = true) — pattern calqué sur
 * EnsureShowcaseModuleMiddleware (module.showcase).
 *
 * Placé APRÈS le middleware `tenant` (company courante déjà résolue) ;
 * kill switch opérationnel : désactiver le flag ⇒ 403 immédiat sur la
 * gestion, sans toucher aux données. La surface publique n'emprunte PAS
 * ce middleware : elle applique ses propres gardes fail-closed
 * (PublicTenantResolver, 404 uniforme si flag coupé — argent = strict).
 *
 * Vit dans le module propriétaire (#8059) : tout `use App\Modules\X\`
 * depuis app/Http est un couplage croisé refusé par la garde CI #5584 —
 * les gates `module.*` nouvelles se déclarent dans leur module.
 */
class EnsureFundraisingModuleMiddleware
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

        if (! $company->hasFeature(FundraisingFeatures::FUNDRAISING)) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Fundraising module.',
            ], 403);
        }

        return $next($request);
    }
}
