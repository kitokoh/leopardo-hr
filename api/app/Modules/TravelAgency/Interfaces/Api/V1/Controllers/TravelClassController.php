<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelClassRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelClassRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelClassResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-305 (#6035) — CRUD des classes de service.
 *
 * Même schéma que les autres contrôleurs référentiels du module : 404 sûr
 * cross-tenant, jamais 403 sur la ressource elle-même.
 */
class TravelClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelClass::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $classes = TravelClass::query()
            ->orderBy('priority')
            ->paginate($perPage);

        return TravelClassResource::collection($classes)->response();
    }

    public function store(StoreTravelClassRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelClass::class)) {
            abort(403);
        }

        $travelClass = TravelClass::query()->create($request->validated());

        return (new TravelClassResource($travelClass))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelClass $travelClass): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelClass->company_id) {
            abort(404);
        }

        return (new TravelClassResource($travelClass))->response();
    }

    public function update(UpdateTravelClassRequest $request, TravelClass $travelClass): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelClass->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelClass)) {
            abort(403);
        }

        $travelClass->update($request->validated());

        return (new TravelClassResource($travelClass))->response();
    }

    public function destroy(Request $request, TravelClass $travelClass): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelClass->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelClass)) {
            abort(403);
        }

        $travelClass->delete();

        return new JsonResponse(null, 204);
    }
}
