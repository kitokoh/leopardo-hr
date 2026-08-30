<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelVehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-306 (#6036) — CRUD de la flotte propre de l'agence.
 *
 * Même schéma que `TravelCarrierController` : 404 sûr cross-tenant, jamais
 * 403 sur la ressource elle-même.
 */
class TravelVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelVehicle::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $vehicles = TravelVehicle::query()
            ->orderBy('code')
            ->paginate($perPage);

        return TravelVehicleResource::collection($vehicles)->response();
    }

    public function store(StoreTravelVehicleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelVehicle::class)) {
            abort(403);
        }

        $vehicle = TravelVehicle::query()->create($request->validated());

        return (new TravelVehicleResource($vehicle->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        return (new TravelVehicleResource($travelVehicle))->response();
    }

    public function update(UpdateTravelVehicleRequest $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelVehicle)) {
            abort(403);
        }

        $travelVehicle->update($request->validated());

        return (new TravelVehicleResource($travelVehicle))->response();
    }

    public function destroy(Request $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelVehicle)) {
            abort(403);
        }

        $travelVehicle->delete();

        return new JsonResponse(null, 204);
    }
}
