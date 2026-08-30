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
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelEquipmentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5805 — CRUD équipements FuelStation : pompes, cuves, produits (FUEL-011).
 *
 * Pompes/cuves sont scopées par station (appartenance vérifiée) ; produits
 * par tenant. Policies deny-by-default via FuelEquipmentPolicy.
 */
class FuelEquipmentController extends Controller
{
    use FuelIndexQueryTrait;

    public function pumps(Request $request, FuelStation $station): JsonResponse
    {
        $this->guardTenant($station, $request->user());

        return $this->index($request, FuelPump::class, ['code', 'created_at'], ['status'], fn (Builder $q) => $q->where('station_id', $station->id));
    }

    public function storePump(Request $request, FuelStation $station): JsonResponse
    {
        $this->guardTenant($station, $request->user());

        return $this->store($request, FuelPump::class, $station, ['company_id' => $station->company_id, 'station_id' => $station->id]);
    }

    public function tanks(Request $request, FuelStation $station): JsonResponse
    {
        $this->guardTenant($station, $request->user());

        return $this->index($request, FuelTank::class, ['code', 'created_at'], ['status', 'product_type'], fn (Builder $q) => $q->where('station_id', $station->id));
    }

    public function storeTank(Request $request, FuelStation $station): JsonResponse
    {
        $this->guardTenant($station, $request->user());

        return $this->store($request, FuelTank::class, $station, ['company_id' => $station->company_id, 'station_id' => $station->id]);
    }

    private function guardTenant(FuelStation $station, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $station->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    public function products(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelProduct::class);

        $query = FuelProduct::query()->where('company_id', $actor->company_id);

        $products = $this->applyFuelIndexQuery($query, $request, ['code', 'name', 'created_at'], ['status']);

        return response()->json(['data' => $products->through(fn (FuelProduct $p): array => $this->productPayload($p))]);
    }

    public function storeProduct(StoreFuelEquipmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelProduct::class);

        /** @var FuelProduct $product */
        $product = FuelProduct::query()->create($request->validated() + ['company_id' => $actor->company_id]);

        return response()->json(['data' => $this->productPayload($product)], 201);
    }

    /**
     * Index générique tenant-scoped pour un modèle équipement.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $sortable
     * @param  list<string>  $filterable
     */
    private function index(Request $request, string $modelClass, array $sortable, array $filterable, ?callable $scope = null): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', $modelClass);

        /** @var Builder<Model> $query */
        $query = $modelClass::query()->where('company_id', $actor->company_id);

        if ($scope !== null) {
            $scope($query);
        }

        $rows = $this->applyFuelIndexQuery($query, $request, $sortable, $filterable);

        return response()->json(['data' => $rows->through(fn (Model $row): array => $this->equipmentPayload($row))]);
    }

    /**
     * @param  array<string, mixed>  $forced
     */
    private function store(Request $request, string $modelClass, FuelStation $station, array $forced): JsonResponse
    {
        $this->assertSolutionActive();
        $this->authorize('create', $modelClass);

        /** @var Model $row */
        $row = $modelClass::query()->create($request->validated() + $forced);

        return response()->json(['data' => $this->equipmentPayload($row)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentPayload(Model $row): array
    {
        $array = $row->toArray();

        unset($array['company_id'], $array['updated_at']);

        return $array;
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

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
