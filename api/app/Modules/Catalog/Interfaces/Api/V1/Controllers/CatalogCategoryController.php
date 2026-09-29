<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\Actions\CreateCatalogCategoryAction;
use App\Modules\Catalog\Application\Actions\DeleteCatalogCategoryAction;
use App\Modules\Catalog\Application\Actions\UpdateCatalogCategoryAction;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\StoreCatalogCategoryRequest;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\UpdateCatalogCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des catégories du catalogue B2B (BC-28 CATALOG, #6881).
 *
 * deny-by-default (CatalogCategoryPolicy) : lecture membres du tenant,
 * gestion réservée principal/rh. Isolation : toute ressource d'un autre
 * tenant répond 404 (pas 403 — leçon fail-closed #3727).
 */
class CatalogCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CatalogCategory::class);

        $query = CatalogCategory::query()->where('company_id', $actor->company_id);

        if ($request->filled('q')) {
            $q = (string) $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('name', 'ilike', '%'.$q.'%')
                ->orWhere('slug', 'ilike', '%'.$q.'%'));
        }

        $categories = $query
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($categories->items())
                ->map(fn (CatalogCategory $c): array => $this->payload($c)),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function store(StoreCatalogCategoryRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', CatalogCategory::class);

        $category = app(CreateCatalogCategoryAction::class)->execute(
            (string) $actor->company_id,
            $request->validated(),
        );

        return response()->json(['data' => $this->payload($category->refresh())], 201);
    }

    public function show(Request $request, CatalogCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $category);

        return response()->json(['data' => $this->payload($category)]);
    }

    public function update(UpdateCatalogCategoryRequest $request, CatalogCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $category);

        $category = app(UpdateCatalogCategoryAction::class)->execute($category, $request->validated());

        return response()->json(['data' => $this->payload($category->refresh())]);
    }

    public function destroy(Request $request, CatalogCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $category);

        app(DeleteCatalogCategoryAction::class)->execute($category);

        return response()->json(['data' => null], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogCategory $category): array
    {
        return [
            'id' => $category->id,
            'company_id' => $category->company_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'position' => $category->position,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
