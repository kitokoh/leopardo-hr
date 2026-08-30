<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelArticleCategory;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelArticleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelArticleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelArticleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-901 (#6104) — Articles & catégories (CRUD, statuts, modération).
 * Statuts draft/published/flagged ; publication horodatée ; modération
 * tracée (moderated_by/moderated_at). Cross-tenant → 404 sûr.
 */
class TravelArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelArticle::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $articles = TravelArticle::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return TravelArticleResource::collection($articles)->response();
    }

    public function store(StoreTravelArticleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelArticle::class)) {
            abort(403);
        }

        $data = $request->validated();

        $article = TravelArticle::query()->create([
            'company_id' => $actor->company_id,
            ...$data,
            'author_type' => 'employee',
            'author_id' => $actor->id,
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return (new TravelArticleResource($article))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelArticle)) {
            abort(403);
        }

        return (new TravelArticleResource($travelArticle))->response();
    }

    public function update(UpdateTravelArticleRequest $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelArticle)) {
            abort(403);
        }

        $data = $request->validated();

        if (($data['status'] ?? null) === 'published' && $travelArticle->published_at === null) {
            $data['published_at'] = now();
        }

        $travelArticle->update($data);

        return (new TravelArticleResource($travelArticle->refresh()))->response();
    }

    /**
     * Modération : draft/published → flagged (ou re-publication).
     */
    public function moderate(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelArticle)) {
            abort(403);
        }

        $status = (string) $request->json('status');

        if (! in_array($status, ['draft', 'published', 'flagged'], true)) {
            abort(422, 'Invalid moderation status.');
        }

        $travelArticle->forceFill([
            'status' => $status,
            'moderated_by_user_id' => $actor->id,
            'moderated_at' => now(),
            'published_at' => $status === 'published' ? ($travelArticle->published_at ?? now()) : $travelArticle->published_at,
        ])->save();

        return (new TravelArticleResource($travelArticle->refresh()))->response();
    }

    public function destroy(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelArticle)) {
            abort(403);
        }

        $travelArticle->delete();

        return new JsonResponse(null, 204);
    }

    // ── Catégories (CRUD léger) ──────────────────────────────────────────

    public function indexCategories(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelArticle::class)) {
            abort(403);
        }

        $categories = TravelArticleCategory::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return new JsonResponse(['data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelArticle::class)) {
            abort(403);
        }

        $slug = trim((string) $request->json('slug'));
        $name = trim((string) $request->json('name'));

        if ($slug === '' || $name === '') {
            abort(422, 'Category slug and name are required.');
        }

        $exists = TravelArticleCategory::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            abort(422, 'Category slug already exists for this tenant.');
        }

        $category = TravelArticleCategory::query()->create([
            'company_id' => $actor->company_id,
            'slug' => $slug,
            'name' => $name,
        ]);

        return new JsonResponse(['data' => $category], 201);
    }
}
