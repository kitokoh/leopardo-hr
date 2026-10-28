<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCommentRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCommentResource;
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

    /**
     * Création d'un commentaire sur un article.
     *
     * #8396 — l'article vient de l'URL (`{travelArticle}` du groupe
     * `/articles/.../comments`) ; le corps ne doit pas pouvoir le contredire.
     */
    public function store(StoreTravelCommentRequest $request, ?TravelArticle $travelArticle = null): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelComment::class)) {
            abort(403);
        }

        $articleId = $travelArticle instanceof TravelArticle
            ? $travelArticle->id
            : (int) $request->validated('article_id');

        if ($articleId <= 0) {
            abort(422, 'Article cible introuvable.');
        }

        $body = $request->validated('content') ?? $request->validated('body');

        $comment = TravelComment::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => $articleId,
            'author_type' => 'employee',
            'author_id' => $actor->id,
            'content_redacted' => trim((string) $body),
            'status' => 'pending',
        ]);

        return (new TravelCommentResource($comment))->response()->setStatusCode(201);
    }

    /**
     * Modération : pending → approved | rejected | flagged (signalement).
     */
    public function moderate(Request $request, TravelComment $travelComment, ?string $presetStatus = null): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelComment->company_id) {
            abort(404);
        }

        if ($actor->cannot('moderate', $travelComment)) {
            abort(403);
        }

        $status = $presetStatus ?? (string) $request->json('status');

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
     *
     * #8396 — la méthode se contentait de passer le statut à `flagged` et
     * n'écrivait ni `report_reason` ni `reported_at`, pourtant créés par la
     * migration 6105 et documentés au contrat. Le motif du signalement — la
     * seule information dont un modérateur a besoin — était donc perdu, et un
     * second signalement écrasait silencieusement le premier au lieu d'être
     * refusé. Le statut attendu par le contrat est `reported`.
     */
    public function report(Request $request, TravelComment $travelComment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelComment->company_id) {
            abort(404);
        }

        if ($travelComment->getAttribute('status') === 'reported') {
            abort(422, 'Ce commentaire est déjà signalé.');
        }

        $travelComment->forceFill([
            'status' => 'reported',
            'report_reason' => $request->json('reason') !== null
                ? mb_substr(trim((string) $request->json('reason')), 0, 255)
                : null,
            'reported_at' => now(),
        ])->save();

        return (new TravelCommentResource($travelComment->refresh()))->response();
    }

    public function destroy(Request $request, TravelComment $travelComment): JsonResponse
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
    }

    public function approve(Request $request, TravelComment $travelComment): JsonResponse
    {
        return $this->moderate($request, $travelComment, 'approved');
    }

    public function reject(Request $request, TravelComment $travelComment): JsonResponse
    {
        return $this->moderate($request, $travelComment, 'rejected');
    }
}
