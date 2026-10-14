<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Models\VtcMarketplaceOperator;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Support\VtcTrackingCode;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use App\Shared\Services\PublicCommerce\TrackingSecretService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        private readonly TrackingSecretService $trackingSecrets,
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
        return array_values($this->eligibleOperators()
            ->map(fn (Company $company): string => (string) $company->id)
            ->all());
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
            fn (Company $company): bool => $this->isPublishedOperator($company),
        );
    }

    /**
     * Opt-in marketplace d'une société : ligne d'annuaire `active`
     * (#8423, M-10). La vérification vit ICI (module propriétaire de
     * l'annuaire) — jamais sur `Company` (règle d'isolation #5584 :
     * Core/Tenant n'importe pas un module).
     */
    private function isPublishedOperator(Company $company): bool
    {
        return VtcMarketplaceOperator::query()
            ->active()
            ->where('company_id', $company->id)
            ->exists();
    }

    /**
     * Exécute `$callback(VtcRide $ride)` dans le tenant de la course
     * identifiée par référence + code de suivi (M-14, pattern
     * `HospitalityPublicPropertyResolver::withinReservation`).
     *
     * Anti-énumération : la référence est unique PAR TENANT — la recherche
     * est donc cross-tenant, BORNÉE aux opérateurs opt-in (annuaire M-10,
     * jamais de lecture non bornée), et le code est vérifié par comparaison
     * de hash TIMING-SAFE sur CHAQUE candidat : référence inconnue, course
     * hors marketplace et code invalide produisent le MÊME 404, sans
     * révéler laquelle des conditions a échoué. Les gardes société
     * (statut, feature, opt-in) sont re-vérifiées à la lecture : une
     * société retirée de l'annuaire voit ses courses se dé-suivre
     * immédiatement (fail-closed).
     *
     * @template T
     *
     * @param  Closure(VtcRide): T  $callback
     * @return T
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (404)
     */
    public function withinRide(string $reference, string $plainCode, Closure $callback): mixed
    {
        $code = VtcTrackingCode::normalize($plainCode);

        if ($code === '') {
            abort(404);
        }

        $eligible = $this->eligibleCompanyIds();

        $candidates = $eligible === []
            ? collect()
            : DB::table($this->tenantTable('vtc_rides'))
                ->whereIn('company_id', $eligible)
                ->where('reference', $reference)
                ->select(['id', 'company_id', 'tracking_code_hash'])
                ->get();

        foreach ($candidates as $candidate) {
            /** @var array<string, mixed> $row */
            $row = get_object_vars($candidate);
            $storedHash = $row['tracking_code_hash'] ?? null;

            // Hash absent (course antérieure au suivi invité) : ne matche
            // jamais (fail-closed, TrackingSecretService).
            if (! is_string($storedHash) || ! $this->trackingSecrets->matches($code, $storedHash)) {
                continue;
            }

            $companyId = $row['company_id'] ?? null;
            $rideId = $row['id'] ?? null;

            if (! is_scalar($companyId) || ! is_numeric($rideId)) {
                continue;
            }

            /** @var Company|null $company */
            $company = Company::query()->find((string) $companyId);

            if (! $company instanceof Company) {
                abort(404);
            }

            $company = $this->publicTenants->assertAccessible(
                $company,
                'vtc',
                fn (Company $candidateCompany): bool => $this->isPublishedOperator($candidateCompany),
            );

            return $this->publicTenants->withinTenant($company, function () use ($rideId, $callback): mixed {
                /** @var VtcRide|null $ride */
                $ride = VtcRide::query()->whereKey((int) $rideId)->first();

                if (! $ride instanceof VtcRide) {
                    abort(404);
                }

                return $callback($ride);
            });
        }

        abort(404);
    }

    /**
     * Table tenant qualifiée pour une lecture cross-tenant bornée (schema
     * partagé `shared_tenants`, ADR-0028) — miroir du helper homonyme de
     * `HospitalityPublicPropertyResolver` (duplication assumée entre
     * résolveurs verticaux, garde d'isolation #5584).
     */
    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }
}
