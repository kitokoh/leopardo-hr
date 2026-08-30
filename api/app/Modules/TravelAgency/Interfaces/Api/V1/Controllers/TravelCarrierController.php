<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCarrierRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelCarrierRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCarrierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-304 (#6034) — CRUD des compagnies de transport.
 *
 * Même schéma que `TravelStationController`/`TravelOfficeController` : 404
 * sûr cross-tenant, jamais 403 sur la ressource elle-même.
 */
class TravelCarrierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelCarrier::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $carriers = TravelCarrier::query()
            ->orderBy('name')
            ->paginate($perPage);

        return TravelCarrierResource::collection($carriers)->response();
    }

    public function store(StoreTravelCarrierRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelCarrier::class)) {
            abort(403);
        }

        $carrier = TravelCarrier::query()->create($request->validated());

        return (new TravelCarrierResource($carrier))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelCarrier $travelCarrier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCarrier->company_id) {
            abort(404);
        }

        return (new TravelCarrierResource($travelCarrier))->response();
    }

    public function update(UpdateTravelCarrierRequest $request, TravelCarrier $travelCarrier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCarrier->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelCarrier)) {
            abort(403);
        }

        $travelCarrier->update($request->validated());

        return (new TravelCarrierResource($travelCarrier))->response();
    }

    public function destroy(Request $request, TravelCarrier $travelCarrier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCarrier->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelCarrier)) {
            abort(403);
        }

        $travelCarrier->delete();

        return new JsonResponse(null, 204);
    }
}
