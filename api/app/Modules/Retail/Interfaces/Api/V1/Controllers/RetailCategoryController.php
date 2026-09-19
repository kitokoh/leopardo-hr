<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailCategoryRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\UpdateRetailCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestion des catégories du module Retail (BC-17 RETAIL, #7672).
 *
 * deny-by-default (RetailCategoryPolicy) : lecture membres du tenant,
 * gestion réservée principal/rh. Isolation : toute ressource d'un autre
 * tenant répond 404 (pas 403 — leçon fail-closed #3727).
 */
class RetailCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailCategory::class);

        $query = RetailCategory::query()->where('company_id', $actor->company_id);

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
                ->map(fn (RetailCategory $c): array => $this->payload($c)),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function store(StoreRetailCategoryRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailCategory::class);

        $category = RetailCategory::query()->create([
            'company_id' => $actor->company_id,
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug(
                (string) $request->input('slug', Str::slug((string) $request->input('name'))),
                (string) $actor->company_id
            ),
            'parent_id' => $request->input('parent_id'),
            'position' => $request->integer('position', 0),
        ]);

        return response()->json(['data' => $this->payload($category->refresh())], 201);
    }

    public function show(Request $request, RetailCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $category);

        return response()->json(['data' => $this->payload($category)]);
    }

    public function update(UpdateRetailCategoryRequest $request, RetailCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $category);

        $category->update([
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug(
                (string) $request->input('slug', Str::slug((string) $request->input('name'))),
                (string) $actor->company_id,
                (int) $category->id
            ),
            'parent_id' => $request->input('parent_id'),
            'position' => $request->integer('position', (int) $category->position),
        ]);

        return response()->json(['data' => $this->payload($category->refresh())]);
    }

    public function destroy(Request $request, RetailCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['data' => null], 200);
    }

    /**
     * Slug unique par tenant : suffixe numérique (-2, -3…) en cas de collision.
     */
    private function uniqueSlug(string $slug, string $companyId, int $ignoreId = 0): string
    {
        $base = $slug;
        $candidate = $base;
        $suffix = 2;

        while (RetailCategory::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->where('id', '!=', $ignoreId)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RetailCategory $category): array
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
