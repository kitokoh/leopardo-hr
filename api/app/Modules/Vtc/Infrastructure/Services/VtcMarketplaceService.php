<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Models\VtcMarketplaceOperator;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Support\Collection;

/**
 * #8423 (M-11) — Marketplace publique VTC/taxi : éligibilité des opérateurs.
 *
 * Agrégation CROSS-TENANT volontaire et bornée, calquée sur
 * `TravelMarketplaceService` (#7737) : seules les sociétés OPT-IN sont
 * visibles — opt-in = ligne `active` de l'annuaire public
 * `vtc_marketplace_operators` (M-10, équivalent VTC du jeton boutique actif
 * travel) ET feature `vtc` active (kill switch plateforme respecté via
 * `Company::hasFeature()`) ET société ni suspendue ni expirée.
 *
 * Toute requête cross-tenant de la marketplace est explicitement bornée par
 * ces identifiants (`whereIn('company_id', …)`) : jamais de lecture non
 * bornée. La résolution PAR RESSOURCE (slug opérateur, référence de course)
 * délègue l'invariant société et la bascule tenant au socle BOS-050
 * {@see PublicTenantResolver} (fail-closed, 404 uniforme).
 */
final class VtcMarketplaceService
{
    public function __construct(
        private readonly PublicTenantResolver $publicTenants,
    ) {}

    /**
     * Sociétés éligibles à la marketplace publique VTC (annuaire M-11).
     *
     * @return Collection<int, Company>
     */
    public function eligibleOperators(): Collection
    {
        $companyIds = VtcMarketplaceOperator::query()
            ->active()
            ->pluck('company_id')
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return collect();
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->whereNotIn('status', ['suspended', 'expired'])
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature('vtc'))
            ->values();
    }

    /**
     * Identifiants des sociétés éligibles — borne des recherches
     * cross-tenant (suivi de course M-14). Jamais exposés au client.
     *
     * @return list<string>
     */
    public function eligibleCompanyIds(): array
    {
        return $this->eligibleOperators()
            ->map(fn (Company $company): string => (string) $company->id)
            ->all();
    }

    /**
     * Opérateur résolu par son slug public, fail-closed (M-12/M-13) : 404
     * uniforme si slug inconnu, société suspendue/expirée, feature `vtc`
     * absente ou publication marketplace inactive — jamais un 403 qui
     * révélerait l'existence de la société (anti-énumération).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (404)
     */
    public function operatorBySlug(string $slug): Company
    {
        return $this->publicTenants->companyBySlug(
            $slug,
            'vtc',
            fn (Company $company): bool => $company->isVtcMarketplaceOperator(),
        );
    }
}
