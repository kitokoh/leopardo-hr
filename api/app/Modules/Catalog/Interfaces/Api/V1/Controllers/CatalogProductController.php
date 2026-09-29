<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\Actions\CreateCatalogProductAction;
use App\Modules\Catalog\Application\Actions\DeleteCatalogProductAction;
use App\Modules\Catalog\Application\Actions\TransitionCatalogProductStatusAction;
use App\Modules\Catalog\Application\Actions\UpdateCatalogProductAction;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\StoreCatalogProductRequest;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\UpdateCatalogProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // C-CURRENCY #6886 : devise par défaut = devise du tenant quand le
        // formulaire n'en fournit pas (spec §8 « par produit ou défaut tenant »).
        $product = app(CreateCatalogProductAction::class)->execute(
            (string) $actor->company_id,
            $request->validated(),
            (string) currentCompany()->currency,
        );

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

        $product = app(UpdateCatalogProductAction::class)->execute($product, $request->validated());

        return response()->json(['data' => $this->payload($product)]);
    }

    public function destroy(Request $request, CatalogProduct $product): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $product);

        app(DeleteCatalogProductAction::class)->execute($product);

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

    private function setStatus(Request $request, CatalogProduct $product, CatalogProductStatus $status): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($product->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('publish', $product);

        $product = app(TransitionCatalogProductStatusAction::class)->execute($product, $status);

        return response()->json(['data' => $this->payload($product)]);
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
