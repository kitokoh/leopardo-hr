<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Issue #7737 — Marketplace publique inter-agences (épic #7736).
 *
 * Agrégation CROSS-TENANT volontaire et bornée : seules les agences OPT-IN
 * sont visibles — opt-in = jeton boutique publique ACTIF (TRAVEL-1001)
 * ET feature `travelagency` active (kill switch plateforme respecté via
 * `Company::hasFeature()`). Toute requête est explicitement contrainte par
 * `whereIn('company_id', $eligibles)` : jamais de lecture non bornée.
 *
 * Les villes (`travel_cities`) sont un référentiel PAR TENANT : deux agences
 * desservant Douala possèdent chacune leur ligne. La marketplace agrège donc
 * par IDENTITÉ GÉOGRAPHIQUE (nom insensible à la casse + pays ISO2) : un
 * `city_id` fourni par le client est résolu vers ce couple, puis la recherche
 * matche les trajets de TOUTES les agences éligibles desservant cette ville.
 */
final class TravelMarketplaceService
{
    /**
     * Tenants opt-in : jeton boutique actif + feature `travelagency`.
     *
     * @return list<string>
     */
    public function eligibleCompanyIds(): array
    {
        $companyIds = TravelPublicShopToken::query()
            ->withoutGlobalScope('company')
            ->where('active', true)
            ->pluck('company_id')
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return [];
        }

        return array_values(Company::query()
            ->whereIn('id', $companyIds)
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature('travelagency'))
            ->map(fn (Company $company): string => (string) $company->id)
            ->values()
            ->all());
    }

    /**
     * Villes desservies (autocomplete) : origines et destinations des trajets
     * PUBLIÉS à venir des agences éligibles, dédupliquées par (nom, pays).
     * L'`id` retourné est un représentant : la recherche résout n'importe
     * quel id de ville éligible vers son identité géographique.
     *
     * @return list<array{id: int, name: string, country_iso2: string, region: string|null}>
     */
    public function cities(): array
    {
        $eligible = $this->eligibleCompanyIds();

        if ($eligible === []) {
            return [];
        }

        $routeIds = TravelTrip::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('status', TripStatus::PUBLISHED)
            ->whereDate('departure_date', '>=', now()->toDateString())
            ->pluck('route_id')
            ->unique()
            ->values();

        if ($routeIds->isEmpty()) {
            return [];
        }

        $cityIds = TravelRoute::query()
            ->withoutGlobalScope('company')
            ->whereIn('id', $routeIds)
            ->get(['origin_city_id', 'destination_city_id'])
            ->flatMap(fn (TravelRoute $route): array => [$route->origin_city_id, $route->destination_city_id])
            ->unique()
            ->values();

        return array_values(TravelCity::query()
            ->withoutGlobalScope('company')
            ->whereIn('id', $cityIds)
            ->whereIn('company_id', $eligible)
            ->get()
            ->unique(fn (TravelCity $city): string => $this->geoKey($city))
            ->sortBy(fn (TravelCity $city): string => mb_strtolower($city->name))
            ->map(fn (TravelCity $city): array => [
                'id' => (int) $city->id,
                'name' => $city->name,
                'country_iso2' => $city->country_iso2,
                'region' => $city->region,
            ])
            ->values()
            ->all());
    }

    /**
     * Recherche agrégée cross-tenant des trajets publiés des agences opt-in.
     *
     * @return LengthAwarePaginator<int, TravelTrip>
     */
    public function searchTrips(
        ?int $originCityId,
        ?int $destinationCityId,
        ?string $date,
        int $perPage,
    ): LengthAwarePaginator {
        $eligible = $this->eligibleCompanyIds();

        $query = TravelTrip::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('status', TripStatus::PUBLISHED)
            ->with(['prices', 'route.originCity', 'route.destinationCity'])
            ->withCount(['seats as available_seats' => fn (Builder $q) => $q->where('status', SeatStatus::FREE)]);

        if ($eligible === []) {
            $query->whereRaw('1 = 0');
        }

        if ($date !== null && $date !== '') {
            $query->whereDate('departure_date', $date);
        } else {
            $query->whereDate('departure_date', '>=', now()->toDateString());
        }

        $this->applyCityFilter($query, $eligible, $originCityId, 'originCity');
        $this->applyCityFilter($query, $eligible, $destinationCityId, 'destinationCity');

        return $query
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * Trajet publié d'une agence éligible (résolution tenant PAR TRAJET) —
     * null sinon : la marketplace ne sert JAMAIS un trajet d'un tenant
     * non opt-in (fail-closed 404 côté contrôleur).
     */
    public function findEligibleTrip(int $tripId): ?TravelTrip
    {
        $eligible = $this->eligibleCompanyIds();

        if ($eligible === []) {
            return null;
        }

        return TravelTrip::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('status', TripStatus::PUBLISHED)
            ->find($tripId);
    }

    /**
     * Tenant propriétaire d'un trajet (pour poser le contexte via
     * TenantManager::withinTenant avant toute écriture).
     */
    public function companyFor(TravelTrip $trip): ?Company
    {
        return Company::query()->find($trip->company_id);
    }

    /**
     * Filtre ville par identité géographique (nom + pays), cross-tenant.
     * Un id de ville inconnu des agences éligibles → aucun résultat
     * (fail-closed : jamais de repli silencieux sur « toutes les villes »).
     *
     * @param  Builder<TravelTrip>  $query
     * @param  list<string>  $eligible
     */
    private function applyCityFilter(Builder $query, array $eligible, ?int $cityId, string $relation): void
    {
        if ($cityId === null) {
            return;
        }

        $city = $eligible === [] ? null : TravelCity::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->find($cityId);

        if (! $city instanceof TravelCity) {
            $query->whereRaw('1 = 0');

            return;
        }

        $name = mb_strtolower($city->name);
        $iso2 = strtoupper($city->country_iso2);

        $query->whereHas(
            'route.'.$relation,
            fn (Builder $q) => $q
                ->whereRaw('LOWER(name) = ?', [$name])
                ->whereRaw('UPPER(country_iso2) = ?', [$iso2]),
        );
    }

    private function geoKey(TravelCity $city): string
    {
        return mb_strtolower($city->name).'|'.strtoupper($city->country_iso2);
    }
}
