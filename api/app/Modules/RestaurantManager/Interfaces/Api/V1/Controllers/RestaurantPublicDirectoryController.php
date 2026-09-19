<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantEstablishmentType;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryCache;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPublicBranchResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * RESTO-901 (#7746) — Annuaire PUBLIC des restaurants + profil public par slug.
 *
 * Routes isolées SANS auth (`throttle:shop-public`, pattern BC-27/BC-28) :
 *   GET /public/restaurants        → annuaire (q, city, type, cuisine, near)
 *   GET /public/restaurants/{slug} → profil + horaires + menu publié
 *
 * Garde-fous multitenancy (preset) :
 *  - seules les branches `is_public = true` ET actives, de sociétés ni
 *    suspendues ni expirées avec le flag `restaurantmanager`, sont visibles ;
 *  - DTO public STRICT (RestaurantPublicBranchResource / payload profil) :
 *    jamais de `company_id` ni d'ID interne brut — le slug EST l'identifiant ;
 *  - 404 fail-closed uniforme (slug inconnu, profil dépublié, société
 *    suspendue) — jamais un 403 qui révélerait l'existence de la ressource ;
 *  - l'annuaire traverse les tenants du schéma partagé (`shared_tenants`,
 *    tenancy « schema » verrouillée — pattern PlatformMetricsOverview) ; le
 *    profil re-résout ensuite SON tenant via TenantManager::withinTenant.
 *
 * Recherche par proximité : formule haversine en SQL (`near=lat,lng` +
 * `radius_km`, défaut 10 km, max 50 km), tri par distance ; sinon tri par
 * nom. Pagination bornée à 50/page. Le profil public est mis en cache
 * (RestaurantPublicDirectoryCache, TTL borné, invalidation à la mutation).
 */
class RestaurantPublicDirectoryController extends Controller
{
    private const DEFAULT_RADIUS_KM = 10.0;

    private const MAX_PER_PAGE = 50;

    /**
     * Distance haversine (km) — bornée par least/greatest : un arrondi
     * flottant hors du domaine d'acos() ferait échouer la requête PostgreSQL.
     * Bindings attendus : [lat, lng, lat].
     */
    private const HAVERSINE_SQL = '(6371 * acos(least(1.0, greatest(-1.0, '
        .'cos(radians(?)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(?)) '
        .'+ sin(radians(?)) * sin(radians(b.latitude))))))';

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly RestaurantPublicDirectoryCache $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'string', Rule::in(RestaurantEstablishmentType::values())],
            'cuisine' => ['sometimes', 'string', 'max:40'],
            'near' => ['sometimes', 'string', 'regex:/^-?\d{1,2}(\.\d+)?,-?\d{1,3}(\.\d+)?$/'],
            'radius_km' => ['sometimes', 'numeric', 'min:0.1', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $this->publicBranchesQuery()
            ->select([
                'b.public_slug',
                'b.name',
                'b.establishment_type',
                'b.cuisine_types',
                'b.city',
                'b.public_description',
                'b.cover_image_url',
                'b.latitude',
                'b.longitude',
            ]);

        $like = $this->isPostgres() ? 'ilike' : 'like';

        if (isset($filters['q']) && is_string($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('b.name', $like, $term)
                    ->orWhere('b.public_description', $like, $term);
            });
        }

        if (isset($filters['city']) && is_string($filters['city'])) {
            $query->where('b.city', $like, addcslashes($filters['city'], '%_\\'));
        }

        if (isset($filters['type']) && is_string($filters['type'])) {
            $query->where('b.establishment_type', $filters['type']);
        }

        if (isset($filters['cuisine']) && is_string($filters['cuisine'])) {
            if ($this->isPostgres()) {
                $query->whereRaw('b.cuisine_types::jsonb @> ?::jsonb', [json_encode([$filters['cuisine']], JSON_THROW_ON_ERROR)]);
            } else {
                $query->where('b.cuisine_types', 'like', '%"'.addcslashes($filters['cuisine'], '%_\\').'"%');
            }
        }

        $near = isset($filters['near']) && is_string($filters['near']) ? $filters['near'] : null;

        if ($near !== null) {
            [$lat, $lng] = array_map(floatval(...), explode(',', $near, 2));
            abort_if($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0, 422);

            $radiusKm = isset($filters['radius_km']) && is_numeric($filters['radius_km'])
                ? min((float) $filters['radius_km'], 50.0)
                : self::DEFAULT_RADIUS_KM;

            $query->selectRaw(self::HAVERSINE_SQL.' as distance_km', [$lat, $lng, $lat])
                ->whereNotNull('b.latitude')
                ->whereNotNull('b.longitude')
                ->whereRaw(self::HAVERSINE_SQL.' <= ?', [$lat, $lng, $lat, $radiusKm])
                ->orderBy('distance_km');
        } else {
            $query->orderBy('b.name');
        }

        $perPage = isset($filters['per_page']) && is_numeric($filters['per_page'])
            ? max(1, min(self::MAX_PER_PAGE, (int) $filters['per_page']))
            : 20;

        return RestaurantPublicBranchResource::collection($query->paginate($perPage))->response();
    }

    public function show(string $slug): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->cache->remember($slug, fn (): ?array => $this->resolveProfile($slug));

        if ($payload === null) {
            abort(404);
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Branches publiques éligibles à l'annuaire : `is_public = true`, branche
     * active, société ni suspendue ni expirée ET flag `restaurantmanager`
     * (fail-closed : une verticale désactivée disparaît de l'annuaire).
     */
    private function publicBranchesQuery(): Builder
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
    private function resolveProfile(string $slug): ?array
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
                'hours' => $this->publicHours($branch),
                'menu' => $this->publishedMenu($branch),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publicHours(RestaurantBranch $branch): array
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
     * null). DTO public sans ID interne de produit ; `image_asset_id` est la
     * référence média déjà exposée par le menu public RESTO-805.
     *
     * @return array<int, array<string, mixed>>
     */
    private function publishedMenu(RestaurantBranch $branch): array
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

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /**
     * Table tenant qualifiée : les données restaurant vivent dans le schéma
     * partagé `shared_tenants` (tenancy « schema » verrouillée) — pattern
     * PlatformMetricsOverviewController.
     */
    private function tenantTable(string $table): string
    {
        return $this->isPostgres() ? 'shared_tenants.'.$table : $table;
    }
}
