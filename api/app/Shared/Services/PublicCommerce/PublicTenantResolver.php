<?php

declare(strict_types=1);

namespace App\Shared\Services\PublicCommerce;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Closure;

/**
 * BOS-050 (#8208) — Résolution du tenant sur les surfaces publiques.
 *
 * Mutualise l'invariant partagé par les surfaces publiques transactionnelles
 * (travel, retail/market, catalog, showcase, restaurant, hospitality) —
 * auparavant ré-implémenté à l'identique dans 6+ middlewares/résolveurs :
 *
 *  1. résolution fail-closed : slug inconnu, société `suspended`/`expired`,
 *     feature verticale absente ou garde métier (opt-in) non satisfaite →
 *     échec UNIFORME (404 par défaut, jamais un 403 qui révélerait
 *     l'existence de la ressource — anti-énumération) ;
 *  2. exécution dans le contexte tenant : marqueur `tenant_scope_required`
 *     + `TenantManager::withinTenant()` (scope BelongsToCompany actif →
 *     aucune fuite cross-tenant), avec restauration de l'état précédent en
 *     `finally` (imbrication sûre).
 *
 * Hors scope de ce résolveur : la résolution par ressource bornée (branche,
 * établissement, réservation+secret) qui reste portée par les résolveurs
 * métier — eux-mêmes appelés à consommer {@see assertAccessible()} et
 * {@see withinTenant()} pour l'invariant commun.
 */
final class PublicTenantResolver
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Résout une société par son slug public, fail-closed.
     *
     * @param  string  $slug  slug public (paramètre de route)
     * @param  string|null  $feature  feature flag exigé (fail-closed) — `null`
     *                                uniquement pour les surfaces historiques sans flag vertical
     *                                (ex. vitrine showcase) ; toute surface qui a un flag DOIT le passer
     * @param  null|Closure(Company): bool  $optIn  garde métier additionnelle
     *                                              (boutique activée, ressource publiée…) — `false` → échec uniforme
     * @param  int  $failureStatus  404 (défaut, anti-énumération) ou 401 (jetons)
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException ($failureStatus)
     */
    public function companyBySlug(
        string $slug,
        ?string $feature,
        ?Closure $optIn = null,
        int $failureStatus = 404,
    ): Company {
        $slug = trim($slug);

        /** @var Company|null $company */
        $company = $slug === ''
            ? null
            : Company::query()->where('slug', $slug)->first();

        if (! $company instanceof Company) {
            abort($failureStatus);
        }

        return $this->assertAccessible($company, $feature, $optIn, $failureStatus);
    }

    /**
     * Vérifie les gardes d'accès public d'une société déjà résolue (ex. via
     * une ressource bornée) : statut d'abonnement, feature verticale, opt-in
     * métier. Toute condition manquante → échec uniforme.
     *
     * @param  string|null  $feature  feature flag exigé (fail-closed)
     * @param  null|Closure(Company): bool  $optIn  garde métier additionnelle
     * @param  int  $failureStatus  404 (défaut) ou 401 (jetons)
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException ($failureStatus)
     */
    public function assertAccessible(
        Company $company,
        ?string $feature,
        ?Closure $optIn = null,
        int $failureStatus = 404,
    ): Company {
        if (in_array($company->status, ['suspended', 'expired'], true)) {
            abort($failureStatus);
        }

        if ($feature !== null && ! $company->hasFeature($feature)) {
            abort($failureStatus);
        }

        if ($optIn !== null && ! $optIn($company)) {
            abort($failureStatus);
        }

        return $company;
    }

    /**
     * Exécute `$callback` dans le contexte du tenant résolu : marqueur
     * `tenant_scope_required` posé (la garde HTTP refuse toute requête scopée
     * sans tenant) + bascule `TenantManager::withinTenant()`. L'état
     * antérieur du marqueur est restauré en `finally` (imbrication sûre).
     *
     * @template T
     *
     * @param  Closure(Company): T  $callback
     * @return T
     */
    public function withinTenant(Company $company, Closure $callback): mixed
    {
        $hadMarker = app()->bound('tenant_scope_required');

        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, fn (): mixed => $callback($company));
        } finally {
            if ($hadMarker) {
                app()->instance('tenant_scope_required', true);
            } else {
                app()->forgetInstance('tenant_scope_required');
            }
        }
    }
}
