<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCancellationPolicyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-813 (#6103) — Politiques d'annulation configurables.
 *
 * CRUD simple ; la politique est appliquée dans les remboursements via
 * TravelRefundPolicyResolver (TRAVEL-808).
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

        $policies = TravelCancellationPolicy::query()
            ->orderByDesc('created_at')
            ->paginate(max(1, min(1000, (int) $request->query('per_page', 50))));

        return response()->json(['data' => $policies->items(), 'meta' => [
            'current_page' => $policies->currentPage(),
            'last_page' => $policies->lastPage(),
            'per_page' => $policies->perPage(),
            'total' => $policies->total(),
        ]]);
    }

    public function store(StoreTravelCancellationPolicyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelCancellationPolicy::class)) {
            abort(403);
        }

        $policy = TravelCancellationPolicy::query()->create([
            'trip_id' => $request->validated('trip_id') !== null ? (int) $request->validated('trip_id') : null,
            'class_id' => $request->validated('class_id') !== null ? (int) $request->validated('class_id') : null,
            'hours_before_departure' => (int) $request->validated('hours_before_departure'),
            'penalty_percent' => (int) $request->validated('penalty_percent'),
            'refundable' => $request->boolean('refundable', true),
            'created_by_user_id' => $actor->id,
        ]);

        return response()->json(['data' => $policy], 201);
    }

    public function update(StoreTravelCancellationPolicyRequest $request, TravelCancellationPolicy $travelCancellationPolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCancellationPolicy->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelCancellationPolicy)) {
            abort(403);
        }

        $travelCancellationPolicy->update([
            'trip_id' => $request->validated('trip_id') !== null ? (int) $request->validated('trip_id') : null,
            'class_id' => $request->validated('class_id') !== null ? (int) $request->validated('class_id') : null,
            'hours_before_departure' => (int) $request->validated('hours_before_departure'),
            'penalty_percent' => (int) $request->validated('penalty_percent'),
            'refundable' => $request->boolean('refundable', true),
        ]);

        return response()->json(['data' => $travelCancellationPolicy->refresh()]);
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
