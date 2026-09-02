<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantZoneRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantZoneRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-301 (#6182) — CRUD des zones du plan de salle (référentiel restaurant).
 *
 * Toute résolution d'un `{restaurantZone}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantZonePolicy`.
 */
class RestaurantZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantZone::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $zones = RestaurantZone::query()
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantZoneResource::collection($zones)->response();
    }

    /**
     * Zones d'une branche donnée (RESTO-301/#6182) — liste scopée à la branche,
     * 404 si la branche appartient à un autre tenant.
     */
    public function indexForBranch(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('viewAny', RestaurantZone::class)) {
            abort(403);
        }

        $zones = RestaurantZone::query()
            ->where('branch_id', $restaurantBranch->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return RestaurantZoneResource::collection($zones)->response();
    }

    public function store(StoreRestaurantZoneRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantZone::class)) {
            abort(403);
        }

        $zone = RestaurantZone::query()->create($request->validated());

        return (new RestaurantZoneResource($zone))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantZone $restaurantZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantZone->company_id) {
            abort(404);
        }

        return (new RestaurantZoneResource($restaurantZone))->response();
    }

    public function update(UpdateRestaurantZoneRequest $request, RestaurantZone $restaurantZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantZone->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantZone)) {
            abort(403);
        }

        $restaurantZone->update($request->validated());

        return (new RestaurantZoneResource($restaurantZone))->response();
    }

    public function destroy(Request $request, RestaurantZone $restaurantZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantZone->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantZone)) {
            abort(403);
        }

        $restaurantZone->delete();

        return new JsonResponse(null, 204);
    }
}
