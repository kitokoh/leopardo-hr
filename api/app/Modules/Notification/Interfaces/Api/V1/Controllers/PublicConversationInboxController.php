<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Application\DTOs\MessageCursor;
use App\Modules\Notification\Application\Services\PublicConversationService;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Interfaces\Api\V1\Requests\PostPublicConversationMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8760 — Messagerie côté PROFESSIONNEL (Business OS).
 *
 * Routes `auth:sanctum` + middleware `tenant` : c'est la surface interne
 * existante, pas une nouvelle. Le professionnel traite donc les demandes
 * publiques depuis SON Business OS, avec son identité et ses permissions.
 *
 * Accès : managers du tenant cible uniquement (`hasParticipant()`). Un simple
 * employé, même du bon tenant, reçoit 404 — les échanges commerciaux avec les
 * clients ne sont pas dans son périmètre. Un manager d'un AUTRE tenant reçoit
 * 404 également : aucun 403 qui révélerait l'existence du fil.
 */
class PublicConversationInboxController extends Controller
{
    public function __construct(private readonly PublicConversationService $conversations) {}

    /**
     * GET /api/v1/conversations/public — boîte de réception des demandes publiques.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 30)));
        $threads = $this->conversations->threadsForManager($manager, $perPage);

        return new JsonResponse([
            'data' => $threads->map(fn (ConversationThread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'target_slug' => $thread->public_target_slug,
                'status' => $thread->status,
                'last_message_at' => $thread->lastMessageAtIso(),
                'last_message_seq' => $thread->lastSeq(),
                'unread' => $thread->isUnreadFor($manager),
            ])->values(),
        ]);
    }

    /**
     * GET /api/v1/conversations/public/{thread} — un fil + son historique.
     */
    public function show(Request $request, int $thread): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        try {
            $conversation = $this->conversations->threadForManager($manager, $thread);
        } catch (ConversationThreadNotFoundException) {
            return new JsonResponse(['error' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        // Accusé de lecture côté professionnel : réutilise `markReadFor()`,
        // déjà utilisé par le chat interne.
        $conversation->markReadFor($manager);

        return new JsonResponse([
            'data' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'target_slug' => $conversation->public_target_slug,
                'status' => $conversation->status,
                'last_message_seq' => $conversation->lastSeq(),
                'messages' => $conversation->messages->map(fn ($message): array => [
                    'id' => $message->id,
                    'seq' => $message->seq,
                    'from' => $message->isFromPublicClient() ? 'client' : 'professional',
                    'body' => $message->visibleBody(),
                    'deleted' => $message->isDeleted(),
                    'created_at' => $message->createdAtIso(),
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/conversations/public/{thread}/messages — pagination par curseur.
     */
    public function messages(Request $request, int $thread): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        try {
            $conversation = $this->conversations->threadForManager($manager, $thread);
        } catch (ConversationThreadNotFoundException) {
            return new JsonResponse(['error' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        $cursor = MessageCursor::fromQuery(
            $request->has('before_seq') ? (int) $request->integer('before_seq') : null,
            $request->has('after_seq') ? (int) $request->integer('after_seq') : null,
            $request->has('per_page') ? (int) $request->integer('per_page') : null,
        );

        $page = $this->conversations->messagesFor($conversation, $cursor);

        return new JsonResponse([
            'data' => $page['messages']->map(fn ($message): array => [
                'id' => $message->id,
                'seq' => $message->seq,
                'from' => $message->isFromPublicClient() ? 'client' : 'professional',
                'body' => $message->visibleBody(),
                'deleted' => $message->isDeleted(),
                'created_at' => $message->createdAtIso(),
            ])->values(),
            'meta' => [
                'has_more' => $page['has_more'],
                'next_before_seq' => $page['next_before_seq'],
                'last_seq' => $page['last_seq'],
            ],
        ]);
    }

    /**
     * POST /api/v1/conversations/public/{thread}/messages — répondre au client.
     */
    public function storeMessage(PostPublicConversationMessageRequest $request, int $thread): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        try {
            $conversation = $this->conversations->threadForManager($manager, $thread);
        } catch (ConversationThreadNotFoundException) {
            return new JsonResponse(['error' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        $message = $this->conversations->postFromManager($conversation, $manager, (string) $request->validated()['body']);

        return new JsonResponse([
            'data' => [
                'id' => $message->id,
                'seq' => $message->seq,
                'from' => 'professional',
                'body' => $message->visibleBody(),
                'created_at' => $message->createdAtIso(),
            ],
        ], 201);
    }
}
