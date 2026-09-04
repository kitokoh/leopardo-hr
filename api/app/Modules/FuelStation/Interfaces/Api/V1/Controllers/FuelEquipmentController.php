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
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelEquipmentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelEquipmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelMeterRegisterRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelPumpRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelTankRequest;

/**
 * Équipements FuelStation : pompes, cuves, compteurs (FUEL-011, #5805).
 *
 * deny-by-default (FuelEquipmentPolicy) : CRUD manager ; lecture employé du
 * tenant. `kind` (pump|tank|meter) détermine la table cible ; les FKs
 * composites (x, company_id) → fuel_stations rendent le cross-tenant
 * impossible.
 */
class FuelEquipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelPump::class);

        $kind = $request->input('kind', 'pump');

        if (! in_array($kind, ['pump', 'tank', 'meter'], true)) {
            abort(422, 'INVALID_EQUIPMENT_KIND');
        }

        $query = match ($kind) {
            'tank' => FuelTank::query()->where('company_id', $actor->company_id),
            'meter' => FuelMeterRegister::query()->where('company_id', $actor->company_id),
            default => FuelPump::query()->where('company_id', $actor->company_id),
        };

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $items = $query->orderBy('code')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($items->items())->map(fn ($item): array => $this->payload($kind, $item)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(StoreFuelEquipmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelPump::class);

        $kind = $request->input('kind');
        $companyId = (string) $actor->company_id;
        $stationId = (int) $request->input('station_id');

        $this->assertStationInTenant($companyId, $stationId);

        $item = match ($kind) {
            'tank' => FuelTank::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'code' => $request->input('code'),
                'product_type' => $request->input('product_type', 'fuel'),
                'capacity_minor' => $request->input('capacity_minor', 0),
                'current_level_minor' => $request->input('current_level_minor', 0),
                'status' => $request->input('status', 'active'),
            ]),
            'meter' => FuelMeterRegister::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'pump_id' => (int) $request->input('pump_id', 0),
                'meter_code' => $request->input('meter_code', $request->input('code')),
                'meter_type' => $request->input('meter_type', 'electronic'),
                'product_code' => $request->input('product_type'),
                'unit_code' => $request->input('unit_code', 'l'),
                'precision_scale' => $request->input('precision_scale', 0),
                'rollover_limit' => $request->input('rollover_limit'),
                'installed_at' => $request->input('installed_at'),
                'status' => $request->input('status', 'active'),
            ]),
            default => FuelPump::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'code' => $request->input('code'),
                'product_types' => $request->input('product_types', []),
                'status' => $request->input('status', 'active'),
            ]),
        };

        return response()->json(['data' => $this->payload($kind, $item->refresh())], 201);
    }

    public function update(UpdateFuelEquipmentRequest $request, string $kind, int $id): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $item = $this->findInTenant($kind, $id, (string) $actor->company_id);

        $this->authorize('update', $item);

        $item->update($request->validated());

        return response()->json(['data' => $this->payload($kind, $item->refresh())]);
    }

    private function assertStationInTenant(string $companyId, int $stationId): void
    {
        $exists = FuelStation::query()
            ->where('company_id', $companyId)
            ->where('id', $stationId)
            ->exists();

        abort_if(! $exists, 422, 'STATION_OUTSIDE_TENANT');
    }

    private function findInTenant(string $kind, int $id, string $companyId): FuelPump|FuelTank|FuelMeterRegister
    {
        $query = match ($kind) {
            'tank' => FuelTank::query(),
            'meter' => FuelMeterRegister::query(),
            default => FuelPump::query(),
        };

        /** @var FuelPump|FuelTank|FuelMeterRegister|null $item */
        $item = $query->where('company_id', $companyId)->find($id);

        abort_if(! $item instanceof FuelPump && ! $item instanceof FuelTank && ! $item instanceof FuelMeterRegister, 404);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $kind, FuelPump|FuelTank|FuelMeterRegister $item): array
    {
        return [
            'kind' => $kind,
            'id' => $item->id,
            'company_id' => $item->company_id,
            'station_id' => $item->station_id,
            'code' => $kind === 'meter'
                ? (string) ($item->getAttribute('meter_code') ?? $item->getAttribute('code'))
                : (string) $item->getAttribute('code'),
            'status' => $item->status,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

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
        $this->authorize('create', FuelPump::class);

        $kind = $request->input('kind');
        $companyId = (string) $actor->company_id;
        $stationId = (int) $request->input('station_id');

        $this->assertStationInTenant($companyId, $stationId);

        $item = match ($kind) {
            'tank' => FuelTank::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'code' => $request->input('code'),
                'product_type' => $request->input('product_type', 'fuel'),
                'capacity_minor' => $request->input('capacity_minor', 0),
                'current_level_minor' => $request->input('current_level_minor', 0),
                'status' => $request->input('status', 'active'),
            ]),
            'meter' => FuelMeterRegister::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'pump_id' => (int) $request->input('pump_id', 0),
                'meter_code' => $request->input('meter_code', $request->input('code')),
                'meter_type' => $request->input('meter_type', 'electronic'),
                'product_code' => $request->input('product_type'),
                'unit_code' => $request->input('unit_code', 'l'),
                'precision_scale' => $request->input('precision_scale', 0),
                'rollover_limit' => $request->input('rollover_limit'),
                'installed_at' => $request->input('installed_at'),
                'status' => $request->input('status', 'active'),
            ]),
            default => FuelPump::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'code' => $request->input('code'),
                'product_types' => $request->input('product_types', []),
                'status' => $request->input('status', 'active'),
            ]),
        };

        return response()->json(['data' => $this->payload($kind, $item->refresh())], 201);
    }


    private function resolvePump(Request $request, FuelPump $pump): Employee
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $item = $this->findInTenant($kind, $id, (string) $actor->company_id);

        $this->authorize('update', $item);

        $item->update($request->validated());

        return response()->json(['data' => $this->payload($kind, $item->refresh())]);
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