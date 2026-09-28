<?php

declare(strict_types=1);

namespace App\Http\Middleware\Catalog;

use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès public au catalogue B2B d'un tenant (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * BOS-050 (#8208, tranche 2) : la résolution du tenant est déléguée au
 * plumbing mutualisé {@see PublicTenantResolver} — invariant identique à
 * l'implémentation historique :
 *
 *  - résolution fail-closed par slug public (`/public/catalog/{companySlug}`
 *    — le visiteur n'a AUCUN compte) : slug inconnu, company
 *    suspended/expired, ou tenant sans feature flag `b2b_catalog` → 404
 *    uniforme (anti-énumération, le flag n'est jamais divulgué) ;
 *  - exécution dans le contexte tenant : marqueur `tenant_scope_required`
 *    + `TenantManager::withinTenant()` (scope global BelongsToCompany actif
 *    → aucune fuite cross-tenant), état restauré en `finally`.
 */
class EnsureCatalogPublicAccess
{
    public function __construct(private readonly PublicTenantResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolver->companyBySlug(
            (string) $request->route('companySlug', ''),
            'b2b_catalog',
        );

        return $this->resolver->withinTenant($company, fn (): Response => $next($request));
    }
}
