<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Application\DTOs\MessageCursor;
use App\Modules\Notification\Application\Services\PublicConversationService;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Interfaces\Api\V1\Requests\PostPublicConversationMessageRequest;
use App\Modules\Notification\Interfaces\Api\V1\Requests\StartPublicConversationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8760 — Messagerie côté CLIENT de la passerelle publique.
 *
 * `auth:user_api` obligatoire : un client public est un `users.id` (identité
 * déjà existante du produit), pas un employé. Il n'y a **aucun** middleware
 * tenant ici : un client converse avec plusieurs entreprises, et le tenant est
 * résolu côté serveur à partir du `target_slug` du profil public.
 *
 * Toutes les réponses sont volontairement MINIMALES : le client ne voit que le
 * contenu des messages de SON fil, le nom commercial du professionnel et les
 * dates. Aucun identifiant interne de tenant, aucun nom d'employé, aucune
 * donnée RH.
 *
 * ⚠️ Isolation : `threadForClient()` rejoue `belongsToPublicClient()` DANS le
 * contexte du tenant. Changer `{thread}` ou `{companyId}` dans l'URL renvoie
 * 404 sans distinguer « inexistant » de « pas à vous ».
 */
class PublicConversationController extends Controller
{
    public function __construct(private readonly PublicConversationService $conversations) {}

    /**
     * GET /api/v1/public/conversations — « mes conversations ».
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $perPage = max(1, min(50, (int) $request->integer('per_page', 30)));
        $threads = $this->conversations->threadsForClient($client, $perPage);

        return new JsonResponse([
            'data' => $threads->map(fn (ConversationThread $thread): array => $this->summarize($thread))->values(),
        ]);
    }

    /**
     * POST /api/v1/public/conversations — démarrer (ou reprendre) une conversation.
     *
     * Idempotent par (client, tenant, profil) : le client retrouve son fil
     * existant plutôt que d'en empiler un second.
     */
    public function store(StartPublicConversationRequest $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');
        $validated = $request->validated();

        try {
            $result = $this->conversations->startConversation(
                $client,
                (string) $validated['target_type'],
                (string) $validated['target_slug'],
                (string) $validated['body'],
            );
        } catch (ConversationThreadNotFoundException) {
            // Uniforme : profil inexistant, dépublié, suspendu ou verticale
            // inactive → même réponse (anti-énumération).
            return new JsonResponse(['error' => 'PROFILE_NOT_AVAILABLE'], 404);
        }

        return new JsonResponse(
            ['data' => $this->detail($result['thread'])],
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * GET /api/v1/public/conversations/{companyId}/{thread} — un fil + son historique.
     */
    public function show(Request $request, string $companyId, int $thread): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        try {
            $conversation = $this->conversations->threadForClient($client, $companyId, $thread);
        } catch (ConversationThreadNotFoundException) {
            return new JsonResponse(['error' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        $this->conversations->markReadByClient($conversation);

        return new JsonResponse(['data' => $this->detail($conversation)]);
    }

    /**
     * GET /api/v1/public/conversations/{companyId}/{thread}/messages — pagination par curseur.
     *
     * `after_seq` = rattrapage après reconnexion, `before_seq` = remonter
     * l'historique. Jamais de WebSocket : le client polle, comme le chat interne.
     */
    public function messages(Request $request, string $companyId, int $thread): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        try {
            $conversation = $this->conversations->threadForClient($client, $companyId, $thread);
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
            'data' => $page['messages']->map(fn ($message): array => $this->message($message))->values(),
            'meta' => [
                'has_more' => $page['has_more'],
                'next_before_seq' => $page['next_before_seq'],
                'last_seq' => $page['last_seq'],
            ],
        ]);
    }

    /**
     * POST /api/v1/public/conversations/{companyId}/{thread}/messages — répondre.
     */
    public function storeMessage(PostPublicConversationMessageRequest $request, string $companyId, int $thread): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        try {
            $conversation = $this->conversations->threadForClient($client, $companyId, $thread);
        } catch (ConversationThreadNotFoundException) {
            return new JsonResponse(['error' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        $message = $this->conversations->postFromClient($conversation, $client, (string) $request->validated()['body']);

        return new JsonResponse(['data' => $this->message($message)], 201);
    }

    /**
     * Résumé de liste : de quoi afficher une carte sans charger l'historique.
     *
     * @return array<string, mixed>
     */
    private function summarize(ConversationThread $thread): array
    {
        return [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'title' => $thread->title,
            'target_slug' => $thread->public_target_slug,
            'last_message_at' => $thread->lastMessageAtIso(),
            'last_message_seq' => $thread->lastSeq(),
            'unread' => $thread->isUnreadForClient(),
        ];
    }

    /**
     * Détail d'un fil : identifiants publics + historique complet.
     *
     * @return array<string, mixed>
     */
    private function detail(ConversationThread $thread): array
    {
        return $this->summarize($thread) + [
            'status' => $thread->status,
            'messages' => $thread->messages
                ->map(fn ($message): array => $this->message($message))
                ->values(),
        ];
    }

    /**
     * Message exposé au CLIENT : le corps, l'auteur (« vous » ou le nom
     * commercial), la date. Aucun `author_id` employé, aucune pièce jointe
     * interne, aucun identifiant de tenant.
     *
     * @return array<string, mixed>
     */
    private function message(\App\Modules\Notification\Domain\Models\ConversationMessage $message): array
    {
        return [
            'id' => $message->id,
            'seq' => $message->seq,
            'from' => $message->isFromPublicClient() ? 'client' : 'professional',
            'body' => $message->visibleBody(),
            'deleted' => $message->isDeleted(),
            'created_at' => $message->createdAtIso(),
        ];
    }
}
