<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelMeterRegisterRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelPumpRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelTankRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel équipements FuelStation (pompes, cuves, compteurs) —
 * FUEL-011 (#5805).
 *
 * Routes imbriquées sous `/fuel-station/stations/{station}/...` : chaque
 * ressource est résolue tenant-scopée (404 cross-tenant AVANT traitement),
 * manager + solution active (fail-closed), pagination bornée.
 */
class FuelEquipmentController extends Controller
{
    public function pumpsIndex(Request $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('viewAny', FuelPump::class);

        $pumps = FuelPump::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->paginated($pumps->items(), $pumps, fn (FuelPump $pump): array => $this->pumpPayload($pump));
    }

    public function pumpsStore(SaveFuelPumpRequest $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('create', FuelPump::class);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $this->pumpPayload($pump)], 201);
    }

    public function pumpsUpdate(SaveFuelPumpRequest $request, FuelPump $pump): JsonResponse
    {
        $actor = $this->resolvePump($request, $pump);
        $this->authorize('update', $pump);

        $pump->update($request->validated());

        return response()->json(['data' => $this->pumpPayload($pump->refresh())]);
    }

    public function tanksIndex(Request $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('viewAny', FuelTank::class);

        $tanks = FuelTank::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->paginated($tanks->items(), $tanks, fn (FuelTank $tank): array => $this->tankPayload($tank));
    }

    public function tanksStore(SaveFuelTankRequest $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('create', FuelTank::class);

        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $this->tankPayload($tank)], 201);
    }

    public function tanksUpdate(SaveFuelTankRequest $request, FuelTank $tank): JsonResponse
    {
        $actor = $this->resolveTank($request, $tank);
        $this->authorize('update', $tank);

        $tank->update($request->validated());

        return response()->json(['data' => $this->tankPayload($tank->refresh())]);
    }

    public function metersIndex(Request $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('viewAny', FuelMeterRegister::class);

        $meters = FuelMeterRegister::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('meter_code')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->paginated($meters->items(), $meters, fn (FuelMeterRegister $meter): array => $this->meterPayload($meter));
    }

    public function metersStore(SaveFuelMeterRegisterRequest $request, FuelStation $station): JsonResponse
    {
        $actor = $this->resolve($request, $station);
        $this->authorize('create', FuelMeterRegister::class);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $this->meterPayload($meter)], 201);
    }

    public function metersUpdate(SaveFuelMeterRegisterRequest $request, FuelMeterRegister $meter): JsonResponse
    {
        $actor = $this->resolveMeter($request, $meter);
        $this->authorize('update', $meter);

        $meter->update($request->validated());

        return response()->json(['data' => $this->meterPayload($meter->refresh())]);
    }

    private function resolve(Request $request, FuelStation $station): Employee
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        return $actor;
    }

    private function resolvePump(Request $request, FuelPump $pump): Employee
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($pump->company_id !== $actor->company_id) {
            abort(404);
        }

        return $actor;
    }

    private function resolveTank(Request $request, FuelTank $tank): Employee
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($tank->company_id !== $actor->company_id) {
            abort(404);
        }

        return $actor;
    }

    private function resolveMeter(Request $request, FuelMeterRegister $meter): Employee
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($meter->company_id !== $actor->company_id) {
            abort(404);
        }

        return $actor;
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  \Illuminate\Pagination\LengthAwarePaginator<T>  $paginator
     * @param  callable(T): array<string, mixed>  $mapper
     */
    private function paginated(array $items, \Illuminate\Pagination\LengthAwarePaginator $paginator, callable $mapper): JsonResponse
    {
        return response()->json([
            'data' => collect($items)->map($mapper),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function pumpPayload(FuelPump $pump): array
    {
        return [
            'id' => $pump->id,
            'station_id' => $pump->station_id,
            'code' => $pump->code,
            'product_types' => $pump->product_types,
            'status' => $pump->status,
            'created_at' => $pump->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function tankPayload(FuelTank $tank): array
    {
        return [
            'id' => $tank->id,
            'station_id' => $tank->station_id,
            'code' => $tank->code,
            'product_type' => $tank->product_type,
            'capacity_minor' => $tank->capacity_minor,
            'current_level_minor' => $tank->current_level_minor,
            'status' => $tank->status,
            'created_at' => $tank->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function meterPayload(FuelMeterRegister $meter): array
    {
        return [
            'id' => $meter->id,
            'station_id' => $meter->station_id,
            'pump_id' => $meter->pump_id,
            'meter_code' => $meter->meter_code,
            'meter_type' => $meter->meter_type,
            'product_code' => $meter->product_code,
            'unit_code' => $meter->unit_code,
            'precision_scale' => $meter->precision_scale,
            'rollover_limit' => $meter->rollover_limit,
            'installed_at' => $meter->installed_at?->toISOString(),
            'retired_at' => $meter->retired_at?->toISOString(),
            'status' => $meter->status,
            'created_at' => $meter->created_at?->toISOString(),
        ];
    }
}
