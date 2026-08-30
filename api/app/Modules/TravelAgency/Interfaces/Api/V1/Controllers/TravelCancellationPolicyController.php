<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCancellationPolicyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelCancellationPolicyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCancellationPolicyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-813 (#6103) — CRUD des politiques d'annulation configurables.
 *
 * Même contrat que les référentiels du module : `company_id` vérifié avant
 * la Policy (404 sûr cross-tenant), écritures réservées `travel.manage`.
 */
class TravelCancellationPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelCancellationPolicy::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $policies = TravelCancellationPolicy::query()
            ->with(['trip', 'travelClass'])
            ->when($request->query('trip_id'), fn ($q, $tripId) => $q->where('trip_id', $tripId))
            ->when($request->query('class_id'), fn ($q, $classId) => $q->where('class_id', $classId))
            ->when($request->query('active'), fn ($q, $active) => $q->where('is_active', (bool) $active))
            ->orderByDesc('id')
            ->paginate($perPage);

        return TravelCancellationPolicyResource::collection($policies)->response();
    }

    public function store(StoreTravelCancellationPolicyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelCancellationPolicy::class)) {
            abort(403);
        }

        $policy = TravelCancellationPolicy::query()->create(
            array_merge($request->validated(), ['company_id' => $actor->company_id]),
        );

        return (new TravelCancellationPolicyResource($policy))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelCancellationPolicy $travelCancellationPolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCancellationPolicy->company_id) {
            abort(404);
        }

        return (new TravelCancellationPolicyResource($travelCancellationPolicy))->response();
    }

    public function update(UpdateTravelCancellationPolicyRequest $request, TravelCancellationPolicy $travelCancellationPolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCancellationPolicy->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelCancellationPolicy)) {
            abort(403);
        }

        $travelCancellationPolicy->update($request->validated());

        return (new TravelCancellationPolicyResource($travelCancellationPolicy->refresh()))->response();
    }

    public function destroy(Request $request, TravelCancellationPolicy $travelCancellationPolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCancellationPolicy->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelCancellationPolicy)) {
            abort(403);
        }

        $travelCancellationPolicy->delete();

        return new JsonResponse(null, 204);
    }
}
