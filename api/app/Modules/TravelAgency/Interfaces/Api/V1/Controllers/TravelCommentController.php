<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCommentRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCommentResource;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-902 (#6105) — Commentaires (CRUD, modération, signalement).
 * Statuts pending/approved/rejected/flagged ; modération tracée ; contenu
 * borné (max 2000). Cross-tenant → 404 sûr.
 */
class TravelCommentController extends Controller
{
    public function index(Request $request): JsonResponse
 *
 * Contenu borné (3..1000), publication modérée (pending → approved),
 * signalement tracé (motif + horodatage, une seule fois par auteur —
 * contrainte applicative). L'écriture est ouverte aux employés du tenant
 * (engagement), la modération est réservée `travel.manage`.
 */
class TravelCommentController extends Controller
{
    public function index(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelComment::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $comments = TravelComment::query()
            ->when($request->has('article_id'), fn ($query) => $query->where('article_id', (int) $request->query('article_id')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return TravelCommentResource::collection($comments)->response();
    }

    public function store(StoreTravelCommentRequest $request): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $comments = TravelComment::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $article->id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $comments->map(fn (TravelComment $c): array => $this->payload($c))]);
    }

    public function store(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelComment::class)) {
            abort(403);
        }

        $comment = TravelComment::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => (int) $request->validated('article_id'),
            'author_type' => 'employee',
            'author_id' => $actor->id,
            'content_redacted' => trim((string) $request->validated('content')),
            'status' => 'pending',
        ]);

        return (new TravelCommentResource($comment))->response()->setStatusCode(201);
    }

    /**
     * Modération : pending → approved | rejected | flagged (signalement).
     */
    public function moderate(Request $request, TravelComment $travelComment): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'author_name' => ['nullable', 'string', 'max:160'],
        ]);

        $comment = TravelComment::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => $article->id,
            'author_type' => 'employee',
            'author_user_id' => $actor->id,
            'author_name' => $data['author_name'] ?? null,
            'body' => $data['body'],
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->payload($comment)])->setStatusCode(201);
    }

    public function destroy(Request $request, TravelComment $comment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelComment->company_id) {
            abort(404);
        }

        if ($actor->cannot('moderate', $travelComment)) {
            abort(403);
        }

        $status = (string) $request->json('status');

        if (! in_array($status, ['approved', 'rejected', 'flagged'], true)) {
            abort(422, 'Invalid moderation status.');
        }

        $travelComment->forceFill([
            'status' => $status,
            'moderated_by_user_id' => $actor->id,
            'moderated_at' => now(),
        ])->save();

        return (new TravelCommentResource($travelComment->refresh()))->response();
    }

    /**
     * Signalement (tout employé du tenant).
     */
    public function report(Request $request, TravelComment $travelComment): JsonResponse
        if ($comment->company_id !== $actor->company_id) {
            abort(404);
        }

        // L'auteur peut supprimer son commentaire ; les managers modèrent tous.
        if ($comment->author_user_id !== $actor->id
            && ! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $comment->delete();

        return new JsonResponse(null, 204);
    }

    public function approve(Request $request, TravelComment $comment): JsonResponse
    {
        return $this->moderate($request, $comment, 'approved');
    }

    public function reject(Request $request, TravelComment $comment): JsonResponse
    {
        return $this->moderate($request, $comment, 'rejected');
    }

    /**
     * Signalement tracé : motif + horodatage (une seule fois par auteur).
     */
    public function report(Request $request, TravelComment $comment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelComment->company_id) {
            abort(404);
        }

        if ($travelComment->status !== 'flagged') {
            $travelComment->forceFill(['status' => 'flagged', 'moderated_at' => now()])->save();
        }

        return (new TravelCommentResource($travelComment->refresh()))->response();
    }

    public function destroy(Request $request, TravelComment $travelComment): JsonResponse
        if ($comment->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        if ($comment->reported_at !== null) {
            abort(422, 'Commentaire déjà signalé.');
        }

        $comment->forceFill([
            'status' => 'reported',
            'report_reason' => $data['reason'],
            'reported_at' => now(),
        ])->save();

        return response()->json(['data' => $this->payload($comment->refresh())]);
    }

    private function moderate(Request $request, TravelComment $comment, string $status): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelComment->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelComment)) {
            abort(403);
        }

        $travelComment->delete();

        return new JsonResponse(null, 204);
        if ($comment->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $comment->forceFill([
            'status' => $status,
            'moderated_at' => now(),
            'moderated_by_user_id' => $actor->id,
        ])->save();

        return response()->json(['data' => $this->payload($comment->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TravelComment $comment): array
    {
        return [
            'id' => $comment->id,
            'article_id' => $comment->article_id,
            'author_name' => $comment->author_name ?? 'Employé',
            'body' => $comment->body,
            'status' => $comment->status,
            'report_reason' => $comment->report_reason,
            'reported_at' => $comment->reported_at?->toIso8601String(),
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
