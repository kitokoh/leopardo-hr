<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
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

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelComment::class)) {
            abort(403);
        }

        $content = (string) $request->json('content');
        $content = trim($content);

        if ($content === '' || mb_strlen($content) > 2000) {
            abort(422, 'Comment content is required (max 2000 characters).');
        }

        $comment = TravelComment::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => (int) $request->json('article_id'),
            'author_type' => 'employee',
            'author_id' => $actor->id,
            'content_redacted' => $content,
            'status' => 'pending',
        ]);

        return (new TravelCommentResource($comment))->response()->setStatusCode(201);
    }

    /**
     * Modération : pending → approved | rejected | flagged (signalement).
     */
    public function moderate(Request $request, TravelComment $travelComment): JsonResponse
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
}
