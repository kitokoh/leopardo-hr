<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API du catalogue produits FuelStation (FUEL-011, #5805).
 *
 * CRUD manager tenant-scoped : code unique par tenant, unité l|gal,
 * metadata chiffrée au repos (jamais exposée).
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

        $products = $query->orderBy('code')->get();

        return response()->json([
            'data' => $products->map(fn (FuelProduct $product): array => $this->payload($product)),
        ]);
    }

    public function store(StoreFuelProductRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelProduct::class);

        /** @var FuelProduct $product */
        $product = FuelProduct::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'unit_code' => $request->input('unit_code') ?? 'l',
            'status' => $request->input('status') ?? 'active',
        ]));

        return response()->json(['data' => $this->payload($product)], 201);
    }

    public function show(Request $request, FuelProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $product);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function update(StoreFuelProductRequest $request, FuelProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    public function destroy(Request $request, FuelProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(null, 204);
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
}
