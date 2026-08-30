<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelAdvertPositionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-905 (#6108) — Référentiel des positions d'annonces (CRUD tenant-scoped).
 */
class TravelAdvertPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $positions = TravelAdvertPosition::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('code')
            ->get()
            ->map(fn (TravelAdvertPosition $p) => ['id' => $p->id, 'code' => $p->code, 'label' => $p->label]);

        return response()->json(['data' => $positions]);
    }

    public function store(StoreTravelAdvertPositionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $position = TravelAdvertPosition::query()->create([
            'company_id' => $actor->company_id,
            'code' => strtolower(trim((string) $request->validated('code'))),
            'label' => trim((string) $request->validated('label')),
        ]);

        return response()->json(['data' => ['id' => $position->id]], 201);
    }

    public function destroy(Request $request, TravelAdvertPosition $travelAdvertPosition): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPosition->company_id) {
            abort(404);
        }

        $travelAdvertPosition->delete();

        return response()->json(null, 204);
    }
}
