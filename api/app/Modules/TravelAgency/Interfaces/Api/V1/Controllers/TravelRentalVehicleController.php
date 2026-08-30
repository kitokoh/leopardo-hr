<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicleImage;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRentalVehicleImageRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRentalVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelRentalVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRentalVehicleImageResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRentalVehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-319 (#6049) — CRUD des véhicules de location.
 *
 * Même schéma que `TravelVehicleController` : 404 sûr cross-tenant, jamais
 * 403 sur la ressource elle-même. Les images sont des sous-ressources
 * imbriquées : écriture soumise à `TravelRentalVehiclePolicy::update()`
 * (principal/rh/manager du tenant).
 */
class TravelRentalVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelRentalVehicle::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $vehicles = TravelRentalVehicle::query()
            ->with('images')
            ->orderBy('code')
            ->paginate($perPage);

        return TravelRentalVehicleResource::collection($vehicles)->response();
    }

    public function store(StoreTravelRentalVehicleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelRentalVehicle::class)) {
            abort(403);
        }

        $vehicle = TravelRentalVehicle::query()->create($request->validated());

        return (new TravelRentalVehicleResource($vehicle->refresh()->load('images')))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelRentalVehicle $travelRentalVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalVehicle->company_id) {
            abort(404);
        }

        return (new TravelRentalVehicleResource($travelRentalVehicle))->response();
    }

    public function update(UpdateTravelRentalVehicleRequest $request, TravelRentalVehicle $travelRentalVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRentalVehicle)) {
            abort(403);
        }

        $travelRentalVehicle->update($request->validated());

        return (new TravelRentalVehicleResource($travelRentalVehicle))->response();
    }

    public function destroy(Request $request, TravelRentalVehicle $travelRentalVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelRentalVehicle)) {
            abort(403);
        }

        $travelRentalVehicle->delete();

        return new JsonResponse(null, 204);
    }

    public function images(Request $request, TravelRentalVehicle $travelRentalVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalVehicle->company_id) {
            abort(404);
        }

        return TravelRentalVehicleImageResource::collection($travelRentalVehicle->images)->response();
    }

    public function storeImage(StoreTravelRentalVehicleImageRequest $request, TravelRentalVehicle $travelRentalVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRentalVehicle)) {
            abort(403);
        }

        $image = $travelRentalVehicle->images()->create($request->validated());

        return (new TravelRentalVehicleImageResource($image))->response()->setStatusCode(201);
    }

    public function destroyImage(Request $request, TravelRentalVehicle $travelRentalVehicle, TravelRentalVehicleImage $image): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (
            $actor->company_id !== $travelRentalVehicle->company_id
            || $image->company_id !== $travelRentalVehicle->company_id
            || $image->vehicle_id !== $travelRentalVehicle->id
        ) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRentalVehicle)) {
            abort(403);
        }

        $image->delete();

        return new JsonResponse(null, 204);
    }
}
