<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelEquipmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5805 — CRUD équipements FuelStation : pompes, cuves, produits (FUEL-011).
 *
 * Pompes/cuves scopées par station (appartenance vérifiée, 404 cross-tenant) ;
 * produits par tenant. Policies deny-by-default via FuelEquipmentPolicy.
 */
class FuelEquipmentController extends Controller
{
    use FuelIndexQueryTrait;

    public function pumps(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelPump::class);

        $query = FuelPump::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id);

        $pumps = $this->applyFuelIndexQuery($query, $request, ['code', 'created_at'], ['status']);

        return response()->json(['data' => $pumps->through(fn (FuelPump $pump): array => $this->pumpPayload($pump))]);
    }

    public function storePump(StoreFuelEquipmentRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelPump::class);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'code' => $request->validated()['code'],
            'status' => $request->validated()['status'] ?? FuelPump::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->pumpPayload($pump)], 201);
    }

    public function tanks(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelTank::class);

        $query = FuelTank::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id);

        $tanks = $this->applyFuelIndexQuery($query, $request, ['code', 'created_at'], ['status', 'product_type']);

        return response()->json(['data' => $tanks->through(fn (FuelTank $tank): array => $this->tankPayload($tank))]);
    }

    public function storeTank(StoreFuelEquipmentRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelTank::class);

        $validated = $request->validated();

        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'code' => $validated['code'],
            'product_type' => $validated['product_type'] ?? null,
            'capacity_minor' => $validated['capacity_minor'] ?? 0,
            'current_level_minor' => $validated['current_level_minor'] ?? 0,
            'status' => $validated['status'] ?? FuelTank::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->tankPayload($tank)], 201);
    }

    public function products(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelProduct::class);

        $query = FuelProduct::query()->where('company_id', $actor->company_id);

        $products = $this->applyFuelIndexQuery($query, $request, ['code', 'name', 'created_at'], ['status']);

        return response()->json(['data' => $products->through(fn (FuelProduct $product): array => $this->productPayload($product))]);
    }

    public function storeProduct(StoreFuelEquipmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelProduct::class);

        $validated = $request->validated();

        /** @var FuelProduct $product */
        $product = FuelProduct::query()->create([
            'company_id' => $actor->company_id,
            'code' => $validated['code'],
            'name' => $validated['name'] ?? $validated['code'],
            'unit_code' => $validated['unit_code'] ?? 'l',
            'status' => $validated['status'] ?? FuelProduct::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->productPayload($product)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function pumpPayload(FuelPump $pump): array
    {
        return [
            'id' => $pump->id,
            'station_id' => $pump->station_id,
            'code' => $pump->code,
            'status' => $pump->status,
            'created_at' => optional($pump->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
            'created_at' => optional($tank->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(FuelProduct $product): array
    {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'unit_code' => $product->unit_code,
            'status' => $product->status,
            'created_at' => optional($product->created_at)->toIso8601String(),
        ];
    }

    private function guardTenant(FuelStation $station, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $station->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
