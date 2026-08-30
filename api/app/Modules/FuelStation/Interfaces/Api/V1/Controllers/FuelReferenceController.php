<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelReferenceRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelReferenceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel FuelStation — CRUD manager (FUEL-011, issue #5805).
 *
 * Couvre les ressources de référence de la verticale : stations, sites,
 * pompes, cuves, compteurs et produits. RBAC deny-by-default
 * (FuelReferencePolicy — manager uniquement), tri/filtres allowlist,
 * pagination bornée (≤ 100), isolation tenant fail-closed (404).
 */
class FuelReferenceController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', $this->modelClass($resource));

        $query = $this->modelClass($resource)::query()->where('company_id', $actor->company_id);

        foreach (['station_id', 'pump_id', 'status', 'product_type', 'unit_code'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $models = $query
            ->orderBy($this->defaultOrder($resource))
            ->paginate(max(1, min(100, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($models->items())->map(fn (Model $model): array => $this->payload($model)),
            'meta' => [
                'current_page' => $models->currentPage(),
                'last_page' => $models->lastPage(),
                'total' => $models->total(),
                'per_page' => $models->perPage(),
            ],
        ]);
    }

    public function store(StoreFuelReferenceRequest $request, string $resource): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', $this->modelClass($resource));

        $model = $this->modelClass($resource)::query()->create(
            array_merge($request->validated(), ['company_id' => $actor->company_id])
        );

        return response()->json(['data' => $this->payload($model)], 201);
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('view', $this->modelClass($resource));

        $model = $this->findTenantScoped($resource, $id, $actor);

        return response()->json(['data' => $this->payload($model)]);
    }

    public function update(UpdateFuelReferenceRequest $request, string $resource, int $id): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('update', $this->modelClass($resource));

        $model = $this->findTenantScoped($resource, $id, $actor);
        $model->update($request->validated());

        return response()->json(['data' => $this->payload($model->refresh())]);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('delete', $this->modelClass($resource));

        $model = $this->findTenantScoped($resource, $id, $actor);
        $model->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /** @return class-string<Model> */
    private function modelClass(string $resource): string
    {
        return match ($resource) {
            'stations' => FuelStation::class,
            'sites' => FuelSite::class,
            'pumps' => FuelPump::class,
            'tanks' => FuelTank::class,
            'meters' => FuelMeterRegister::class,
            'products' => FuelProduct::class,
            default => abort(404, 'RESOURCE_UNKNOWN'),
        };
    }

    private function defaultOrder(string $resource): string
    {
        return match ($resource) {
            'products' => 'code',
            'meters' => 'meter_code',
            default => 'code',
        };
    }

    private function findTenantScoped(string $resource, int $id, Employee $actor): Model
    {
        $model = $this->modelClass($resource)::query()
            ->where('company_id', $actor->company_id)
            ->find($id);

        if (! $model instanceof Model) {
            abort(404);
        }

        return $model;
    }

    /** @return array<string, mixed> */
    private function payload(Model $model): array
    {
        return array_merge([
            'id' => $model->getAttribute('id'),
            'company_id' => $model->getAttribute('company_id'),
            'status' => $model->getAttribute('status'),
        ], $model->getAttributes());
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
