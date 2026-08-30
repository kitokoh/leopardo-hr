<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRouteRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelRouteRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRouteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-307 (#6037) — CRUD des routes ville→ville.
 *
 * Même schéma que `TravelCarrierController` : 404 sûr cross-tenant, jamais
 * 403 sur la ressource elle-même. Les étapes sont exposées triées par
 * `rank` (relation `stops()`).
 */
class TravelRouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelRoute::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $routes = TravelRoute::query()
            ->with('stops')
            ->orderBy('code')
            ->paginate($perPage);

        return TravelRouteResource::collection($routes)->response();
    }

    public function store(StoreTravelRouteRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelRoute::class)) {
            abort(403);
        }

        $route = TravelRoute::query()->create($request->validated());

        return (new TravelRouteResource($route->refresh()->load('stops')))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelRoute $travelRoute): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        $travelRoute->load('stops');

        return (new TravelRouteResource($travelRoute))->response();
    }

    public function update(UpdateTravelRouteRequest $request, TravelRoute $travelRoute): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRoute)) {
            abort(403);
        }

        $travelRoute->update($request->validated());

        return (new TravelRouteResource($travelRoute->load('stops')))->response();
    }

    public function destroy(Request $request, TravelRoute $travelRoute): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelRoute)) {
            abort(403);
        }

        $travelRoute->delete();

        return new JsonResponse(null, 204);
    }
}
