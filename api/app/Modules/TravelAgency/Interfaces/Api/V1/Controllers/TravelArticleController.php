<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelArticleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-901 (#6104) — Articles & catégories (CRUD, statuts, modération).
 *
 * Publication contrôlée (statut + `published_at` serveur), catégories
 * uniques par tenant (code), signalement tracé (`moderation_note`).
 * Écritures réservées `travel.manage` ; lecture ouverte aux employés du
 * tenant.
 */
class TravelArticleController extends Controller
{
    // ── Catégories ──────────────────────────────────────────────────────────

    public function indexCategories(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $categories = TravelArticleCategory::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('active'), fn ($q, $active) => $q->where('is_active', (bool) $active))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('travel_article_categories', 'code')->where('company_id', $actor->company_id)],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = TravelArticleCategory::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        );

        return response()->json(['data' => $category])->setStatusCode(201);
    }

    public function updateCategory(Request $request, TravelArticleCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $category->update($request->validate([
            'code' => ['sometimes', 'string', 'max:60'],
            'name' => ['sometimes', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $category->refresh()]);
    }

    public function destroyCategory(Request $request, TravelArticleCategory $category): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($category->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $category->delete();

        return new JsonResponse(null, 204);
    }

    // ── Articles ────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $articles = TravelArticle::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $articles->map(fn (TravelArticle $a): array => $this->articlePayload($a)),
            'meta' => ['total' => $articles->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:travel_article_categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:10', 'max:50000'],
            'status' => ['sometimes', 'string', 'in:draft,published'],
        ]);

        $article = TravelArticle::query()->create([
            'company_id' => $actor->company_id,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'body_redacted' => $data['body'],
            'status' => $data['status'] ?? 'draft',
            'author_user_id' => $actor->id,
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return response()->json(['data' => $this->articlePayload($article)])->setStatusCode(201);
    }

    public function show(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => $this->articlePayload($article)]);
    }

    public function update(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $article->update($request->validate([
            'category_id' => ['nullable', 'integer', 'exists:travel_article_categories,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string', 'min:10', 'max:50000'],
            'status' => ['sometimes', 'string', 'in:draft,published,reported,archived'],
        ]));

        return response()->json(['data' => $this->articlePayload($article->refresh())]);
    }

    public function destroy(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $article->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * Publication contrôlée d'un brouillon (statut + horodatage serveur).
     */
    public function publish(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        if ($article->status === 'draft' || $article->status === 'reported') {
            $article->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'moderation_note' => null,
            ])->save();
        }

        return response()->json(['data' => $this->articlePayload($article->refresh())]);
    }

    /**
     * Modération : statut + note (signalement tracé).
     */
    public function moderate(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,reported,archived'],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ]);

        $article->forceFill([
            'status' => $data['status'],
            'moderation_note' => $data['moderation_note'] ?? null,
            'published_at' => $data['status'] === 'published' ? ($article->published_at ?? now()) : $article->published_at,
        ])->save();

        return response()->json(['data' => $this->articlePayload($article->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(TravelArticle $article): array
    {
        return [
            'id' => $article->id,
            'category_id' => $article->category_id,
            'title' => $article->title,
            'body' => $article->body_redacted,
            'status' => $article->status,
            'published_at' => $article->published_at?->toIso8601String(),
            'moderation_note' => $article->moderation_note,
            'created_at' => $article->created_at?->toIso8601String(),
            'likes_count' => $article->likes_count ?? 0,
            'comments_count' => $article->comments_count ?? 0,
            'rating_avg' => $article->rating_avg ?? null,
        ];
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
    }
}
