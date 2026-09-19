<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailProductRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\UpdateRetailProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestion des produits du module Retail (BC-17 RETAIL, #7672).
 *
 * deny-by-default (RetailProductPolicy) : lecture membres du tenant,
 * gestion (CRUD + publication) réservée principal/rh. Isolation : toute
 * ressource d'un autre tenant répond 404 (leçon fail-closed #3727).
 * Rien n'est vendable sans `publish` (statut `published`).
 */
class RetailProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailProduct::class);

        $query = RetailProduct::query()->where('company_id', $actor->company_id);

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
                ->orWhere('slug', 'ilike', '%'.$q.'%')
                ->orWhere('sku', 'ilike', '%'.$q.'%')
                ->orWhere('barcode', 'ilike', '%'.$q.'%'));
        }

        $products = $query
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($products->items())
                ->map(fn (RetailProduct $p): array => $this->payload($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreRetailProductRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailProduct::class);

        $product = RetailProduct::query()->create([
            'company_id' => $actor->company_id,
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug(
                (string) $request->input('slug', Str::slug((string) $request->input('name'))),
                (string) $actor->company_id
            ),
            'sku' => $request->input('sku'),
            'barcode' => $request->input('barcode'),
            'description' => $request->input('description'),
            'price_minor' => $request->integer('price_minor'),
            'cost_minor' => $request->filled('cost_minor') ? $request->integer('cost_minor') : null,
            // Devise par défaut = devise du tenant quand le formulaire n'en
            // fournit pas (pattern Catalog C-CURRENCY #6886).
            'currency' => $request->input('currency') ?? (string) currentCompany()->currency,
            'unit' => $request->input('unit'),
            'image_url' => $request->input('image_url'),
            'status' => $request->input('status', RetailProductStatus::Draft->value),
            'meta' => $request->input('meta'),
        ]);

        return response()->json(['data' => $this->payload($product->refresh())], 201);
    }

    public function show(Request $request, RetailProduct $product): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $product);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function update(UpdateRetailProductRequest $request, RetailProduct $product): JsonResponse
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
            'sku' => $request->input('sku', $product->sku),
            'barcode' => $request->input('barcode'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'price_minor' => $request->integer('price_minor'),
            'cost_minor' => $request->filled('cost_minor') ? $request->integer('cost_minor') : null,
            'currency' => $request->input('currency') ?? $product->currency,
            'unit' => $request->input('unit') ?? $product->unit,
            'image_url' => $request->input('image_url', $product->image_url),
            'status' => $request->input('status') ?? $product->status->value,
            'meta' => $request->input('meta'),
        ]);

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    public function destroy(Request $request, RetailProduct $product): JsonResponse
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

    public function publish(Request $request, RetailProduct $product): JsonResponse
    {
        return $this->setStatus($request, $product, RetailProductStatus::Published);
    }

    public function unpublish(Request $request, RetailProduct $product): JsonResponse
    {
        return $this->setStatus($request, $product, RetailProductStatus::Draft);
    }

    /**
     * Slug unique par tenant : suffixe numérique (-2, -3…) en cas de collision.
     */
    private function uniqueSlug(string $slug, string $companyId, int $ignoreId = 0): string
    {
        $base = $slug;
        $candidate = $base;
        $suffix = 2;

        while (RetailProduct::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->where('id', '!=', $ignoreId)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function setStatus(Request $request, RetailProduct $product, RetailProductStatus $status): JsonResponse
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
    private function payload(RetailProduct $product): array
    {
        return [
            'id' => $product->id,
            'company_id' => $product->company_id,
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'description' => $product->description,
            'price_minor' => $product->price_minor,
            'cost_minor' => $product->cost_minor,
            'currency' => $product->currency,
            'unit' => $product->unit,
            'status' => $product->status->value,
            'online_visible' => (bool) $product->online_visible,
            'image_url' => $product->image_url,
            'meta' => $product->meta,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
