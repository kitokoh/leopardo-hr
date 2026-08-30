<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelTankRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des cuves FuelStation (FUEL-011, #5805).
 *
 * CRUD manager nested sous une station, tenant-scoped via la FK composite
 * (station_id, company_id). Capacités/volumes en unités mineures entières.
 */
class FuelTankController extends Controller
{
    public function index(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelTank::class);

        $tanks = FuelTank::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $tanks->map(fn (FuelTank $tank): array => $this->payload($tank)),
        ]);
    }

    public function store(StoreFuelTankRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelTank::class);

        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'current_level_minor' => $request->input('current_level_minor') ?? 0,
            'status' => $request->input('status') ?? 'active',
        ]));

        return response()->json(['data' => $this->payload($tank)], 201);
    }

    public function show(Request $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($tank->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $tank);

        return response()->json(['data' => $this->payload($tank)]);
    }

    public function update(StoreFuelTankRequest $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($tank->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $tank);

        $tank->update($request->validated());

        return response()->json(['data' => $this->payload($tank->refresh())]);
    }

    public function destroy(Request $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($tank->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $tank);

        $tank->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelTank $tank): array
    {
        return [
            'id' => $tank->id,
            'company_id' => $tank->company_id,
            'station_id' => $tank->station_id,
            'code' => $tank->code,
            'product_type' => $tank->product_type,
            'capacity_minor' => $tank->capacity_minor,
            'current_level_minor' => $tank->current_level_minor,
            'status' => $tank->status,
            'created_at' => $tank->created_at?->toISOString(),
            'updated_at' => $tank->updated_at?->toISOString(),
        ];
    }
}
