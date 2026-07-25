<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use App\Modules\Platform\Infrastructure\Services\PlatformSupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PA2-COMM-012 — Tenant-facing side of the pilot support center: a manager
 * or employee can open a support ticket and reply on their own company's
 * tickets. Triage (status/priority/assignment) is platform-admin-only, see
 * PlatformSupportTicketController.
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly PlatformSupportTicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(PlatformSupportTicket::statuses())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = PlatformSupportTicket::query()
            ->where('company_id', $actor->company_id)
            ->withCount('messages')
            ->orderByDesc('last_message_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $tickets = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (PlatformSupportTicket $t): array => $this->summaryPayload($t))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(PlatformSupportTicket::categories())],
            'priority' => ['nullable', 'string', Rule::in(PlatformSupportTicket::priorities())],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $this->ticketService->openTicket(
            author: $actor,
            subject: $validated['subject'],
            category: $validated['category'],
            body: $validated['message'],
            priority: $validated['priority'] ?? PlatformSupportTicket::PRIORITY_NORMAL,
        );

        return response()->json(['data' => $this->detailPayload($ticket->load('messages'))], 201);
    }

    public function show(Request $request, PlatformSupportTicket $supportTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureCompanyOwnsTicket($supportTicket, $actor);

        return response()->json([
            'data' => $this->detailPayload($supportTicket->load(['messages.authorEmployee:id,first_name,last_name'])),
        ]);
    }

    public function reply(Request $request, PlatformSupportTicket $supportTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureCompanyOwnsTicket($supportTicket, $actor);

        if ($supportTicket->status === PlatformSupportTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is closed. Open a new ticket if you need further help.'],
            ]);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->ticketService->replyAsEmployee($supportTicket, $actor, $validated['message']);

        return response()->json([
            'data' => $this->detailPayload($supportTicket->refresh()->load('messages')),
        ]);
    }

    private function ensureCompanyOwnsTicket(PlatformSupportTicket $ticket, Employee $actor): void
    {
        if ($ticket->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function summaryPayload(PlatformSupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'messages_count' => $ticket->messages_count ?? 0,
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailPayload(PlatformSupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'messages' => $ticket->messages->map(fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'from_platform' => $message->isFromPlatform(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
