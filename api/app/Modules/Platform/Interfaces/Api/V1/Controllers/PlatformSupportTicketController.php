<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use App\Modules\Platform\Infrastructure\Services\PlatformSupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PA2-COMM-012 — Pilot client support center, platform-admin side.
 *
 * Lets a super-admin see every tenant's support conversations, filter by
 * status/priority, reply, and triage (status, priority, assignment).
 */
class PlatformSupportTicketController extends Controller
{
    public function __construct(private readonly PlatformSupportTicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(PlatformSupportTicket::statuses())],
            'priority' => ['nullable', 'string', Rule::in(PlatformSupportTicket::priorities())],
            'company_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PlatformSupportTicket::query()
            ->with(['company:id,name', 'createdBy:id,first_name,last_name,email', 'assignedSuperAdmin:id,name'])
            ->withCount('messages')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('last_message_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }
        if (isset($validated['company_id'])) {
            $query->where('company_id', $validated['company_id']);
        }

        $tickets = $query->paginate((int) ($validated['per_page'] ?? 25));

        $statusCounts = PlatformSupportTicket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (PlatformSupportTicket $t): array => $this->summaryPayload($t))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
                'status_counts' => [
                    'open' => (int) ($statusCounts['open'] ?? 0),
                    'pending' => (int) ($statusCounts['pending'] ?? 0),
                    'resolved' => (int) ($statusCounts['resolved'] ?? 0),
                    'closed' => (int) ($statusCounts['closed'] ?? 0),
                ],
            ],
        ]);
    }

    public function show(PlatformSupportTicket $supportTicket): JsonResponse
    {
        $supportTicket->load([
            'company:id,name',
            'createdBy:id,first_name,last_name,email',
            'assignedSuperAdmin:id,name',
            'messages.authorEmployee:id,first_name,last_name',
            'messages.authorSuperAdmin:id,name',
        ]);

        return response()->json(['data' => $this->detailPayload($supportTicket)]);
    }

    public function reply(Request $request, PlatformSupportTicket $supportTicket): JsonResponse
    {
        /** @var SuperAdmin $actor */
        $actor = $request->user('super_admin_api');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->ticketService->replyAsSuperAdmin($supportTicket, $actor, $validated['message']);

        return response()->json([
            'data' => $this->detailPayload($supportTicket->refresh()->load([
                'company:id,name',
                'createdBy:id,first_name,last_name,email',
                'assignedSuperAdmin:id,name',
                'messages.authorEmployee:id,first_name,last_name',
                'messages.authorSuperAdmin:id,name',
            ])),
        ]);
    }

    public function triage(Request $request, PlatformSupportTicket $supportTicket): JsonResponse
    {
        /** @var SuperAdmin $actor */
        $actor = $request->user('super_admin_api');

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(PlatformSupportTicket::statuses())],
            'priority' => ['nullable', 'string', Rule::in(PlatformSupportTicket::priorities())],
            'assign_to_me' => ['nullable', 'boolean'],
        ]);

        $ticket = $this->ticketService->triage(
            ticket: $supportTicket,
            status: $validated['status'] ?? null,
            priority: $validated['priority'] ?? null,
            assignedSuperAdminId: ($validated['assign_to_me'] ?? false) ? $actor->id : null,
        );

        return response()->json(['data' => $this->summaryPayload($ticket->load(['company:id,name', 'assignedSuperAdmin:id,name']))]);
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
            'company' => $ticket->company ? [
                'id' => $ticket->company->id,
                'name' => $ticket->company->name,
            ] : null,
            'created_by' => $ticket->createdBy ? [
                'id' => $ticket->createdBy->id,
                'name' => trim($ticket->createdBy->first_name.' '.$ticket->createdBy->last_name),
                'email' => $ticket->createdBy->email,
            ] : null,
            'assigned_super_admin' => $ticket->assignedSuperAdmin ? [
                'id' => $ticket->assignedSuperAdmin->id,
                'name' => $ticket->assignedSuperAdmin->name,
            ] : null,
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailPayload(PlatformSupportTicket $ticket): array
    {
        return [
            ...$this->summaryPayload($ticket),
            'messages' => $ticket->messages->map(fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'from_platform' => $message->isFromPlatform(),
                'author_name' => $message->isFromPlatform()
                    ? $message->authorSuperAdmin?->name
                    : trim((string) $message->authorEmployee?->first_name.' '.(string) $message->authorEmployee?->last_name),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
