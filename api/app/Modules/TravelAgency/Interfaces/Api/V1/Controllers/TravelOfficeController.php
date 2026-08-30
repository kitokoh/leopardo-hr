<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelOfficeRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelOfficeRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelOfficeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-303 (#6033) — CRUD des bureaux de vente.
 *
 * Même schéma que `TravelStationController` (TRAVEL-302) : 404 sûr
 * cross-tenant, jamais 403 sur la ressource elle-même.
 */
class TravelOfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelOffice::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $offices = TravelOffice::query()
            ->orderBy('name')
            ->paginate($perPage);

        return TravelOfficeResource::collection($offices)->response();
    }

    public function store(StoreTravelOfficeRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelOffice::class)) {
            abort(403);
        }

        $office = TravelOffice::query()->create($request->validated());

        return (new TravelOfficeResource($office))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelOffice $travelOffice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelOffice->company_id) {
            abort(404);
        }

        return (new TravelOfficeResource($travelOffice))->response();
    }

    public function update(UpdateTravelOfficeRequest $request, TravelOffice $travelOffice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelOffice->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelOffice)) {
            abort(403);
        }

        $travelOffice->update($request->validated());

        return (new TravelOfficeResource($travelOffice))->response();
    }

    public function destroy(Request $request, TravelOffice $travelOffice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelOffice->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelOffice)) {
            abort(403);
        }

        $travelOffice->delete();

        return new JsonResponse(null, 204);
    }
}
