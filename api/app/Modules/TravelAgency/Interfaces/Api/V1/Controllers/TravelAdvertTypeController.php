<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelAdvertTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-905 (#6108) — Référentiel des types d'annonces (CRUD tenant-scoped).
 */
class TravelAdvertTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = TravelAdvertType::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('code')
            ->get()
            ->map(fn (TravelAdvertType $t) => ['id' => $t->id, 'code' => $t->code, 'label' => $t->label]);

        return response()->json(['data' => $types]);
    }

    public function store(StoreTravelAdvertTypeRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $type = TravelAdvertType::query()->create([
            'company_id' => $actor->company_id,
            'code' => strtolower(trim((string) $request->validated('code'))),
            'label' => trim((string) $request->validated('label')),
        ]);

        return response()->json(['data' => ['id' => $type->id]], 201);
    }

    public function destroy(Request $request, TravelAdvertType $travelAdvertType): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertType->company_id) {
            abort(404);
        }

        $travelAdvertType->delete();

        return response()->json(null, 204);
    }
}
