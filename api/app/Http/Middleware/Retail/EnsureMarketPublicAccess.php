<?php

declare(strict_types=1);

namespace App\Http\Middleware\Retail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Support\RetailFeatures;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acces public a la marketplace Leopardo Marche (BC-17 RETAIL, #7807).
 *
 * Deux surfaces publiques (spec §3.1, throttle:shop-public, SANS auth) :
 * - routes AVEC parametre `sellerSlug` (ex. /public/market/sellers/{slug}) :
 *   le tenant est resolu par slug public puis pose via
 *   `TenantManager::withinTenant()` + marqueur `tenant_scope_required`
 *   (pattern calque sur EnsureCatalogPublicAccess, C-PUBLIC #6882) ;
 * - routes cross-tenant (listing/recherche globale, checkout par slug dans
 *   le corps) : AUCUNE resolution ici — les controleurs bornent chaque
 *   requete aux tenants opt-in (RetailMarketplaceService, pattern
 *   marketplace TravelAgency #7737).
 *
 * Fail-closed uniforme 404 (pas de probing) : slug inconnu, company
 * suspendue/expiree, feature flag `retail` absent ou boutique en ligne non
 * activee (l'opt-in n'est jamais divulgue publiquement).
 *
 * BOS-050 (#8208, tranche 4) : la resolution du tenant est deleguee au
 * plumbing mutualise {@see PublicTenantResolver} — invariant identique a
 * l'implementation historique : `companyBySlug($slug, retail, opt-in)` ou
 * la garde opt-in (boutique en ligne activee) est exactement le cas prevu
 * par le socle, puis `withinTenant()` (marqueur `tenant_scope_required`
 * restaure en `finally`, imbrication sure — seul gain de comportement).
 */
class EnsureMarketPublicAccess
{
    public function __construct(private readonly PublicTenantResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = trim((string) $request->route('sellerSlug', ''));

        if ($slug === '') {
            // Route cross-tenant : pas de tenant a resoudre, les controleurs
            // restent bornes aux tenants opt-in (fail-closed).
            return $next($request);
        }

        $company = $this->resolver->companyBySlug(
            $slug,
            RetailFeatures::RETAIL,
            fn (Company $company): bool => RetailOnlineSettings::query()
                ->withoutGlobalScope('company')
                ->where('company_id', (string) $company->id)
                ->where('enabled', true)
                ->exists(),
        );

        return $this->resolver->withinTenant($company, fn (): Response => $next($request));
    }
}
