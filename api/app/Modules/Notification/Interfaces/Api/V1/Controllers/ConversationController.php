<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationMessageResource;
use App\Http\Resources\Api\V1\ConversationThreadResource;
use App\Modules\Notification\Application\DTOs\CreateThreadDTO;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Infrastructure\Services\ConversationService;
use App\Modules\Notification\Interfaces\Api\V1\Requests\PostConversationMessageRequest;
use App\Modules\Notification\Interfaces\Api\V1\Requests\StoreConversationThreadRequest;
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

        $perPage = (int) $request->integer('per_page', 20);
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
}
