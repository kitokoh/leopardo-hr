<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantReviewStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Requêtes de l'annuaire public des restaurants (RESTO-901 #7746).
 *
 * Extrait de `RestaurantPublicDirectoryController` (483 lignes) : la
 * construction de la requête d'annuaire, la résolution du profil par slug et
 * les agrégations d'avis vivaient dans la couche HTTP. Ce sont des règles de
 * visibilité — quelles branches sont publiques, quels avis comptent, quel
 * menu est publié — et elles doivent être testables sans monter une requête
 * HTTP complète.
 *
 * Les garde-fous multitenancy restent ici, à un seul endroit :
 *  - seules les branches `is_public = true` et actives, de sociétés ni
 *    suspendues ni expirées avec le flag `restaurantmanager`, sont visibles ;
 *  - le profil re-résout SON tenant via `TenantManager::withinTenant` ;
 *  - 404 fail-closed : un slug inconnu, dépublié ou d'une société suspendue
 *    renvoie `null`, jamais une distinction qui révélerait l'existence.
 */
class RestaurantPublicDirectoryQueryService
{
    public const DEFAULT_RADIUS_KM = 10.0;

    public const MAX_PER_PAGE = 50;

    /**
     * Distance haversine (km) — bornée par least/greatest : un arrondi
     * flottant hors du domaine d'acos() ferait échouer la requête PostgreSQL.
     * Bindings attendus : [lat, lng, lat].
     */
    private const HAVERSINE_SQL = '(6371 * acos(least(1.0, greatest(-1.0, '
        .'cos(radians(?)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(?)) '
        .'+ sin(radians(?)) * sin(radians(b.latitude))))))';

    /**
     * Mémo par requête des stats d'avis publiés (profil par slug).
     *
     * @var array{rating_avg: float|null, reviews_count: int}|null
     */
    private ?array $ratingStats = null;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly GeoServiceContract $geo,
    ) {}

    /**
     * Branches publiques éligibles à l'annuaire : `is_public = true`, branche
     * active, société ni suspendue ni expirée ET flag `restaurantmanager`
     * (fail-closed : une verticale désactivée disparaît de l'annuaire).
     */
    public function publicBranchesQuery(): Builder
    {
        $query = DB::table($this->tenantTable('restaurant_branches').' as b')
            ->join('companies as c', 'c.id', '=', 'b.company_id')
            ->where('b.is_public', true)
            ->where('b.status', RestaurantRecordStatus::ACTIVE->value)
            ->whereNotNull('b.public_slug')
            ->whereNotIn('c.status', ['suspended', 'expired']);

        if ($this->isPostgres()) {
            $query->whereRaw("coalesce((c.features ->> 'restaurantmanager')::boolean, false) is true");
        }

        return $query;
    }

    /**
     * Profil public complet : identité + horaires + menu PUBLIÉ uniquement
     * (`is_published_online` ET `is_available` ET produit actif). Retourne
     * null (→ 404 fail-closed) si le slug est inconnu, dépublié, ou si la
     * société est suspendue/expirée/sans flag.
     *
     * @return array<string, mixed>|null
     */
    public function resolveProfile(string $slug): ?array
    {
        $row = $this->publicBranchesQuery()
            ->where('b.public_slug', $slug)
            ->select(['b.id', 'b.company_id'])
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $branchRow */
        $branchRow = get_object_vars($row);
        $companyId = $branchRow['company_id'] ?? null;
        $branchId = $branchRow['id'] ?? null;

        if (! is_scalar($companyId) || ! is_numeric($branchId)) {
            return null;
        }

        /** @var Company|null $company */
        $company = Company::query()
            ->where('id', (string) $companyId)
            ->whereNotIn('status', ['suspended', 'expired'])
            ->first();

        if (! $company instanceof Company || ! $company->hasFeature('restaurantmanager')) {
            return null;
        }

        return $this->tenantManager->withinTenant($company, function () use ($branchId): ?array {
            /** @var RestaurantBranch|null $branch */
            $branch = RestaurantBranch::query()
                ->where('id', (int) $branchId)
                ->where('is_public', true)
                ->where('status', RestaurantRecordStatus::ACTIVE)
                ->first();

            if (! $branch instanceof RestaurantBranch) {
                return null;
            }

            $rating = $this->publishedRatingStats($branch);

            return [
                'slug' => $branch->public_slug,
                'name' => $branch->name,
                'establishment_type' => $branch->establishment_type?->value,
                'cuisine_types' => $branch->cuisine_types,
                'city' => $branch->city,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'description' => $branch->public_description,
                'cover_image_url' => $branch->cover_image_url,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'currency' => $branch->currency,
                'timezone' => $branch->timezone,
                'rating_avg' => $rating['rating_avg'],
                'reviews_count' => $rating['reviews_count'],
                'hours' => $this->publicHours($branch),
                'menu' => $this->publishedMenu($branch),
            ];
        });
    }

    /**
     * RESTO-902 (#7747) — note moyenne + nombre d'avis PUBLIÉS de la branche
     * (les avis pending/rejected ne comptent jamais). Calcul à la lecture,
     * mémorisé par requête puis porté par le cache du profil public
     * (RestaurantPublicDirectoryCache) — invalidé à chaque publication/rejet
     * (RestaurantReviewModerationController). Pas de compteur dénormalisé :
     * zéro dérive possible, la modération fait foi.
     *
     * @return array{rating_avg: float|null, reviews_count: int}
     */
    public function publishedRatingStats(RestaurantBranch $branch): array
    {
        if ($this->ratingStats !== null) {
            return $this->ratingStats;
        }

        /** @var object{reviews_count: int|string|null, rating_avg: float|int|string|null}|null $row */
        $row = RestaurantReview::query()
            ->where('branch_id', $branch->id)
            ->where('status', RestaurantReviewStatus::PUBLISHED->value)
            ->toBase()
            ->selectRaw('count(*) as reviews_count, avg(rating) as rating_avg')
            ->first();

        if ($row === null) {
            return $this->ratingStats = ['rating_avg' => null, 'reviews_count' => 0];
        }

        $count = is_numeric($row->reviews_count) ? (int) $row->reviews_count : 0;

        return $this->ratingStats = [
            'rating_avg' => $count > 0 && is_numeric($row->rating_avg)
                ? round((float) $row->rating_avg, 2)
                : null,
            'reviews_count' => $count,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicHours(RestaurantBranch $branch): array
    {
        return RestaurantHour::query()
            ->where('branch_id', $branch->id)
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (RestaurantHour $hour): array => [
                'day_of_week' => $hour->day_of_week,
                'opens_at' => $hour->opens_at,
                'closes_at' => $hour->closes_at,
                'is_closed' => (bool) $hour->is_closed,
            ])
            ->values()
            ->all();
    }

    /**
     * Menu publié : catégories actives → produits `is_published_online` ET
     * `is_available` ET actifs, de la branche ou company-wide (branch_id
     * null). DTO public sans ID interne de produit : `code` est l'identifiant
     * MÉTIER commandé par la commande publique RESTO-902 (même contrat que la
     * boutique publique RESTO-805) ; `image_asset_id` est la référence média
     * déjà exposée par le menu public RESTO-805.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publishedMenu(RestaurantBranch $branch): array
    {
        $products = RestaurantProduct::query()
            ->where('is_published_online', true)
            ->where('is_available', true)
            ->where('status', RestaurantRecordStatus::ACTIVE)
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)->orWhereNull('branch_id');
            })
            ->orderBy('name')
            ->get()
            ->groupBy('category_id');

        return RestaurantCategory::query()
            ->where('status', RestaurantRecordStatus::ACTIVE)
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)->orWhereNull('branch_id');
            })
            ->orderBy('sort_order')
            ->get()
            ->map(function (RestaurantCategory $category) use ($products): ?array {
                /** @var \Illuminate\Support\Collection<int, RestaurantProduct> $items */
                $items = $products->get($category->id, collect());

                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'name' => $category->name,
                    'sort_order' => $category->sort_order,
                    'products' => $items
                        ->map(fn (RestaurantProduct $product): array => [
                            'code' => $product->code,
                            'name' => $product->name,
                            'description' => $product->description_redacted,
                            'price_minor' => (int) $product->price_minor,
                            'currency' => $product->currency,
                            'image_asset_id' => $product->image_asset_id,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Ajoute les colonnes de note d'avis publiés à la requête d'annuaire.
     *
     * RESTO-902 (#7747) — calculées en sous-requêtes sur le schéma partagé
     * (pas de compteur dénormalisé — zéro dérive, la modération fait foi).
     * `avg(rating)` retourne un numeric → round(x, 2) valide en PostgreSQL ET
     * SQLite.
     */
    public function withPublishedRatingColumns(Builder $query): Builder
    {
        $reviewsTable = $this->tenantTable('restaurant_reviews');

        return $query->selectRaw(
            '(select round(avg(rv.rating), 2) from '.$reviewsTable.' rv'
            .' where rv.branch_id = b.id and rv.company_id = b.company_id'
            .' and rv.status = ?) as rating_avg',
            ['published']
        )->selectRaw(
            '(select count(*) from '.$reviewsTable.' rv2'
            .' where rv2.branch_id = b.id and rv2.company_id = b.company_id'
            .' and rv2.status = ?) as reviews_count',
            ['published']
        );
    }

    /**
     * Applique le filtre de proximité (core geo ou repli haversine).
     */
    public function applyNear(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        if ($this->geoPilotEnabled()) {
            // GEO-06 (#8355, BC-34 GEO) — calcul délégué au core géospatial
            // (moteur PostGIS ou repli Haversine INTERNE au module geo, jamais
            // de SQL local) : ids + distances, puis contrainte de la requête
            // annuaire (bindings paramétrés).
            $this->applyGeoCoreNear($query, new GeoPoint($lat, $lng), $radiusKm);

            return;
        }

        // Repli legacy (pilote inactif) — retiré à la généralisation du core
        // geo (garde GEO-07 #8356, durcissement #8380).
        $query->selectRaw(self::HAVERSINE_SQL.' as distance_km', [$lat, $lng, $lat])
            ->whereNotNull('b.latitude')
            ->whereNotNull('b.longitude')
            ->whereRaw(self::HAVERSINE_SQL.' <= ?', [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km');
    }

    public function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /**
     * Pilote GEO-06 (#8355) : l'annuaire délègue ses calculs de proximité au
     * core géospatial (BC-34) — kill switch opérationnel de rollout
     * (config `geo.pilots.restaurant_directory`, défaut off).
     */
    public function geoPilotEnabled(): bool
    {
        return (bool) config('geo.pilots.restaurant_directory', false);
    }

    /**
     * Table tenant qualifiée : les données restaurant vivent dans le schéma
     * partagé `shared_tenants` (tenancy « schema » verrouillée) — pattern
     * PlatformMetricsOverviewController.
     */
    public function tenantTable(string $table): string
    {
        return $this->isPostgres() ? 'shared_tenants.'.$table : $table;
    }

    /**
     * GEO-06 (#8355, BC-34 GEO) — recherche par proximité via le core geo.
     *
     * Le moteur `geo` (type `restaurant` de la registry, vue d'adaptation
     * scopée « annuaire public ») retourne les branches dans le rayon, triées
     * par distance ; la requête annuaire est ensuite contrainte à ces ids et
     * ordonnée par la distance fournie par le core — comportement identique au
     * repli legacy (rayon défaut 10 km, max 50, tri croissant, `distance_km`
     * exposé). Bindings paramétrés partout (spec §7).
     */
    private function applyGeoCoreNear(Builder $query, GeoPoint $center, float $radiusKm): void
    {
        $results = $this->geo->nearest('restaurant', $center, $radiusKm, 100);

        /** @var array<int, float> $distancesKmById */
        $distancesKmById = [];

        foreach ($results as $result) {
            $payload = $result->toArray();
            $id = $payload['id'];

            if (is_int($id)) {
                $distancesKmById[$id] = round($payload['distance_meters'] / 1000.0, 2);
            }
        }

        if ($distancesKmById === []) {
            // Aucune branche publique dans le rayon → page vide garantie.
            $query->whereRaw('1 = 0');

            return;
        }

        // Distance par branche en CASE paramétré (select + tri identiques au
        // contrat legacy `distance_km`, bindings uniquement).
        $caseSql = 'case b.id '.implode(' ', array_fill(0, count($distancesKmById), 'when ? then ?')).' end';

        /** @var list<int|float> $caseBindings */
        $caseBindings = [];

        foreach ($distancesKmById as $branchId => $distanceKm) {
            $caseBindings[] = $branchId;
            $caseBindings[] = $distanceKm;
        }

        $query->whereNotNull('b.latitude')
            ->whereNotNull('b.longitude')
            ->whereIn('b.id', array_keys($distancesKmById))
            ->selectRaw($caseSql.' as distance_km', $caseBindings)
            ->orderByRaw($caseSql, $caseBindings);
    }
}
