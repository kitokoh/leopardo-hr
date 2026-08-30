<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue produits FuelStation (FUEL-011, #5805). deny-by-default
 * (FuelProductPolicy) : CRUD manager, lecture employé du tenant.
 */
class FuelProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelProduct::class);

        $query = FuelProduct::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $products = $query->orderBy('code')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($products->items())->map(fn (FuelProduct $p): array => $this->payload($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreFuelProductRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelProduct::class);

        $product = FuelProduct::query()->create([
            'company_id' => $actor->company_id,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'unit_code' => $request->input('unit_code', 'l'),
            'status' => $request->input('status', 'active'),
            'metadata' => $request->input('metadata'),
        ]);

        return response()->json(['data' => $this->payload($product->refresh())], 201);
    }

    public function update(StoreFuelProductRequest $request, FuelProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelProduct $product): array
    {
        return [
            'id' => $product->id,
            'company_id' => $product->company_id,
            'code' => $product->code,
            'name' => $product->name,
            'unit_code' => $product->unit_code,
            'status' => $product->status,
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
