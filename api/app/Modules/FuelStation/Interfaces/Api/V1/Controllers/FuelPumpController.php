<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelPumpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des pompes FuelStation (FUEL-011, #5805).
 *
 * CRUD manager nested sous une station, tenant-scoped via la FK composite
 * (station_id, company_id). Les compteurs (registers) d'une pompe sont
 * exposés via `FuelMeterReadingController` (FUEL-004).
 */
class FuelPumpController extends Controller
{
    public function index(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelPump::class);

        $query = FuelPump::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $pumps = $query->orderBy('code')->get();

        return response()->json([
            'data' => $pumps->map(fn (FuelPump $pump): array => $this->payload($pump)),
        ]);
    }

    public function store(StoreFuelPumpRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelPump::class);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'status' => $request->input('status') ?? 'active',
        ]));

        return response()->json(['data' => $this->payload($pump)], 201);
    }

    public function show(Request $request, FuelPump $pump): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($pump->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $pump);

        return response()->json(['data' => $this->payload($pump)]);
    }

    public function update(StoreFuelPumpRequest $request, FuelPump $pump): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($pump->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $pump);

        $pump->update($request->validated());

        return response()->json(['data' => $this->payload($pump->refresh())]);
    }

    public function destroy(Request $request, FuelPump $pump): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($pump->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $pump);

        $pump->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelPump $pump): array
    {
        return [
            'id' => $pump->id,
            'company_id' => $pump->company_id,
            'station_id' => $pump->station_id,
            'code' => $pump->code,
            'product_types' => $pump->product_types,
            'status' => $pump->status,
            'created_at' => $pump->created_at?->toISOString(),
            'updated_at' => $pump->updated_at?->toISOString(),
        ];
    }
}
