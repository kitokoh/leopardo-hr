<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\StoreCatalogProductRequest;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\UpdateCatalogProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestion des produits du catalogue B2B (BC-28 CATALOG, #6881).
 *
 * deny-by-default (CatalogProductPolicy) : lecture membres du tenant,
 * gestion (CRUD + publication) réservée principal/rh. Isolation : toute
 * ressource d'un autre tenant répond 404 (leçon fail-closed #3727).
 * Rien n'est exposé publiquement sans `publish` (statut `published`).
 */
class CatalogProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CatalogProduct::class);

        $query = CatalogProduct::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('q')) {
            $q = (string) $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('name', 'ilike', '%'.$q.'%')
                ->orWhere('slug', 'ilike', '%'.$q.'%'));
        }

        $products = $query
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($products->items())
                ->map(fn (CatalogProduct $p): array => $this->payload($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreCatalogProductRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', CatalogProduct::class);

        $product = CatalogProduct::query()->create([
            'company_id' => $actor->company_id,
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug(
                (string) $request->input('slug', Str::slug((string) $request->input('name'))),
                (string) $actor->company_id
            ),
            'description' => $request->input('description'),
            'price_minor' => $request->integer('price_minor'),
            'currency' => $request->input('currency'),
            'unit' => $request->input('unit', 'piece'),
            'status' => $request->input('status', CatalogProductStatus::Draft->value),
            'meta' => $request->input('meta'),
        ]);

        return response()->json(['data' => $this->payload($product->refresh())], 201);
    }

    public function show(Request $request, CatalogProduct $product): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $product);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function update(UpdateCatalogProductRequest $request, CatalogProduct $product): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $product);

        $product->update([
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug(
                (string) $request->input('slug', Str::slug((string) $request->input('name'))),
                (string) $actor->company_id,
                (int) $product->id
            ),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'price_minor' => $request->integer('price_minor'),
            'currency' => $request->input('currency'),
            'unit' => $request->input('unit'),
            'status' => $request->input('status'),
            'meta' => $request->input('meta'),
        ]);

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    public function destroy(Request $request, CatalogProduct $product): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['data' => null], 200);
    }

    public function publish(Request $request, CatalogProduct $product): JsonResponse
    {
        return $this->setStatus($request, $product, CatalogProductStatus::Published);
    }

    public function unpublish(Request $request, CatalogProduct $product): JsonResponse
    {
        return $this->setStatus($request, $product, CatalogProductStatus::Draft);
    }

    /**
     * Slug unique par tenant : suffixe numérique (-2, -3…) en cas de collision.
     */
    private function uniqueSlug(string $slug, string $companyId, int $ignoreId = 0): string
    {
        $base = $slug;
        $candidate = $base;
        $suffix = 2;

        while (CatalogProduct::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->where('id', '!=', $ignoreId)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function setStatus(Request $request, CatalogProduct $product, CatalogProductStatus $status): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('publish', $product);

        $product->update(['status' => $status->value]);

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogProduct $product): array
    {
        return [
            'id' => $product->id,
            'company_id' => $product->company_id,
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price_minor' => $product->price_minor,
            'currency' => $product->currency,
            'unit' => $product->unit,
            'status' => $product->status->value,
            'meta' => $product->meta,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
