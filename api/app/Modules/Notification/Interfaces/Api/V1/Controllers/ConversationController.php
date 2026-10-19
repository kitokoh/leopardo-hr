<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationMessageResource;
use App\Http\Resources\Api\V1\ConversationThreadResource;
use App\Modules\Notification\Application\DTOs\CreateGroupThreadDTO;
use App\Modules\Notification\Application\DTOs\CreateThreadDTO;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationParticipant;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Infrastructure\Services\ConversationService;
use App\Modules\Notification\Interfaces\Api\V1\Requests\AddConversationParticipantRequest;
use App\Modules\Notification\Interfaces\Api\V1\Requests\PostConversationMessageRequest;
use App\Modules\Notification\Interfaces\Api\V1\Requests\StoreConversationThreadRequest;
use App\Modules\Notification\Interfaces\Api\V1\Requests\StoreGroupConversationThreadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PA2-COMM-002 — Employee/manager discussion threads.
 *
 * Every endpoint here is tenant-scoped (through `company_id`) and further
 * restricted to the two thread participants (the employee and their
 * manager): an employee only ever sees their own threads, and a manager
 * only sees threads where they are the assigned manager.
 *
 * #8746 [BOS-CHAT] — extends the same surface with GROUP threads
 * (`kind = 'group'`): creation, participant listing, add / remove / leave.
 * The binary `direct` contract above is untouched, so no existing client
 * breaks.
 */
class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversationService) {}

    /**
     * GET /conversations — list threads visible to the authenticated actor.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $threads = $this->conversationService->threadsFor($actor, $perPage);

        return ConversationThreadResource::collection($threads)->response();
    }

    /**
     * POST /conversations — create a new thread with its first message.
     */
    public function store(StoreConversationThreadRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $thread = $this->conversationService->createThread(
            $actor,
            new CreateThreadDTO(
                title: $validated['title'],
                body: $validated['body'],
                subjectType: $validated['subject_type'] ?? null,
                subjectId: isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
                recipientId: isset($validated['recipient_id']) ? (int) $validated['recipient_id'] : null,
            ),
            $request->file('attachment'),
        );

        return (new ConversationThreadResource($thread))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /conversations/{thread} — thread detail with its full message
     * history, and marks it as read for the requesting actor.
     */
    public function show(Request $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $conversationThread = $this->conversationService->findForActor($thread, $actor);
        $conversationThread->markReadFor($actor);

        return (new ConversationThreadResource($conversationThread))->response();
    }

    /**
     * POST /conversations/{thread}/messages — post a reply (with at most
     * one optional attachment) in an existing thread.
     */
    public function storeMessage(PostConversationMessageRequest $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        $message = $this->conversationService->postMessage(
            $conversationThread,
            $actor,
            $request->validated('body'),
            $request->file('attachment'),
        );

        return (new ConversationMessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /conversations/{thread}/messages/{message}/attachment
     */
    public function downloadAttachment(Request $request, int $thread, int $message): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        /** @var ConversationMessage|null $conversationMessage */
        $conversationMessage = $conversationThread->messages()
            ->whereKey($message)
            ->first();

        if ($conversationMessage === null || ! $conversationMessage->hasAttachment()) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($conversationMessage->attachment_path)) {
            abort(404);
        }

        return $disk->download(
            $conversationMessage->attachment_path,
            $conversationMessage->attachment_original_name,
        );
    }

    // ──────────────────────────────────────────────────────────────
    // #8746 [BOS-CHAT] — Groupes
    //
    // Chaque action passe par `findForActor()` (scope tenant + appartenance)
    // puis par `ConversationParticipantPolicy`. Un non-participant reçoit 404,
    // jamais 403 : un 403 révélerait l'existence du fil à un tiers.
    // ──────────────────────────────────────────────────────────────

    /**
     * POST /conversations/groups — crée un groupe avec son message d'ouverture.
     */
    public function storeGroup(StoreGroupConversationThreadRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $thread = $this->conversationService->createGroup(
            $actor,
            new CreateGroupThreadDTO(
                title: $validated['title'],
                body: $validated['body'],
                participantIds: array_map(static fn ($id): int => (int) $id, $validated['participant_ids']),
                subjectType: $validated['subject_type'] ?? null,
                subjectId: isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            ),
            $request->file('attachment'),
        );

        return (new ConversationThreadResource($thread))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /conversations/{thread}/participants — membres du groupe, avec rôle.
     */
    public function participants(Request $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $conversationThread = $this->conversationService->findForActor($thread, $actor);
        $conversationThread->loadMissing('participants.employee:id,first_name,last_name,matricule');

        return response()->json([
            'data' => $conversationThread->participants
                ->map(static fn (ConversationParticipant $participant): array => [
                    'employee_id' => $participant->employee_id,
                    'first_name' => $participant->employee?->first_name,
                    'last_name' => $participant->employee?->last_name,
                    'matricule' => $participant->employee?->matricule,
                    'role' => $participant->role,
                    'last_read_message_id' => $participant->last_read_message_id,
                    'joined_at' => $participant->joined_at->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    /**
     * POST /conversations/{thread}/participants — ajoute un membre (creator/admin).
     */
    public function addParticipant(AddConversationParticipantRequest $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var ConversationThread $conversationThread */
        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        $participant = $this->conversationService->addParticipant(
            $conversationThread,
            $actor,
            (int) $request->validated('employee_id'),
        );

        return response()->json([
            'data' => [
                'employee_id' => $participant->employee_id,
                'role' => $participant->role,
                'joined_at' => $participant->joined_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /conversations/{thread}/participants/{employee} — retire un membre.
     * Auto-retrait délégué à `leave()` (règles distinctes).
     */
    public function removeParticipant(Request $request, int $thread, int $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var ConversationThread $conversationThread */
        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        $this->conversationService->removeParticipant($conversationThread, $actor, $employee);

        return response()->json([], 204);
    }

    /**
     * POST /conversations/{thread}/leave — quitte le groupe.
     */
    public function leave(Request $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var ConversationThread $conversationThread */
        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        $this->conversationService->leave($conversationThread, $actor);

        return response()->json([], 204);
    }
}
