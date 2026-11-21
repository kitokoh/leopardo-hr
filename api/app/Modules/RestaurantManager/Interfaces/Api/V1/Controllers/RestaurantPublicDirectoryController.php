<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantEstablishmentType;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryCache;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryQueryService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPublicBranchResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        private readonly RestaurantPublicDirectoryCache $cache,
        private readonly RestaurantPublicDirectoryQueryService $directory,
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.RestaurantPublicDirectoryQueryService::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $this->directory->publicBranchesQuery()
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

        $this->directory->withPublishedRatingColumns($query);

        $like = $this->directory->isPostgres() ? 'ilike' : 'like';

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
            if ($this->directory->isPostgres()) {
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
                : RestaurantPublicDirectoryQueryService::DEFAULT_RADIUS_KM;

            $this->directory->applyNear($query, $lat, $lng, $radiusKm);
        } else {
            $query->orderBy('b.name');
        }

        $perPage = isset($filters['per_page']) && is_numeric($filters['per_page'])
            ? max(1, min(RestaurantPublicDirectoryQueryService::MAX_PER_PAGE, (int) $filters['per_page']))
            : 20;

        return RestaurantPublicBranchResource::collection($query->paginate($perPage))->response();
    }

    public function show(string $slug): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->cache->remember($slug, fn (): ?array => $this->directory->resolveProfile($slug));

        if ($payload === null) {
            abort(404);
        }

        return response()->json(['data' => $payload]);
    }
}
