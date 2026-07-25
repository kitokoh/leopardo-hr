<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Domain\Models\PlatformSupportMessage;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use Illuminate\Support\Facades\DB;

/**
 * PA2-COMM-012 — Pilot client support center.
 *
 * Centralizes the small set of state transitions around a support ticket
 * (open, reply, triage, resolve) so both the tenant-facing controller and
 * the platform admin controller share the same rules instead of duplicating
 * `last_message_at` bookkeeping and status guards.
 */
class PlatformSupportTicketService
{
    public function openTicket(
        Employee $author,
        string $subject,
        string $category,
        string $body,
        string $priority = PlatformSupportTicket::PRIORITY_NORMAL,
    ): PlatformSupportTicket {
        return DB::transaction(function () use ($author, $subject, $category, $body, $priority): PlatformSupportTicket {
            $now = now();

            /** @var PlatformSupportTicket $ticket */
            $ticket = PlatformSupportTicket::query()->create([
                'company_id' => $author->company_id,
                'created_by_employee_id' => $author->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => PlatformSupportTicket::STATUS_OPEN,
                'last_message_at' => $now,
            ]);

            PlatformSupportMessage::query()->create([
                'platform_support_ticket_id' => $ticket->id,
                'author_employee_id' => $author->id,
                'body' => $body,
                'created_at' => $now,
            ]);

            return $ticket;
        });
    }

    public function replyAsEmployee(PlatformSupportTicket $ticket, Employee $author, string $body): PlatformSupportMessage
    {
        return DB::transaction(function () use ($ticket, $author, $body): PlatformSupportMessage {
            $message = PlatformSupportMessage::query()->create([
                'platform_support_ticket_id' => $ticket->id,
                'author_employee_id' => $author->id,
                'body' => $body,
                'created_at' => now(),
            ]);

            $ticket->forceFill([
                'last_message_at' => $message->created_at,
                // A tenant reply reopens the conversation for the platform
                // team if it had been marked as merely "pending" a client
                // answer; a resolved/closed ticket must be reopened
                // explicitly by triage instead of silently by a stray reply.
                'status' => $ticket->status === PlatformSupportTicket::STATUS_PENDING
                    ? PlatformSupportTicket::STATUS_OPEN
                    : $ticket->status,
            ])->save();

            return $message;
        });
    }

    public function replyAsSuperAdmin(PlatformSupportTicket $ticket, SuperAdmin $author, string $body): PlatformSupportMessage
    {
        return DB::transaction(function () use ($ticket, $author, $body): PlatformSupportMessage {
            $message = PlatformSupportMessage::query()->create([
                'platform_support_ticket_id' => $ticket->id,
                'author_super_admin_id' => $author->id,
                'body' => $body,
                'created_at' => now(),
            ]);

            $ticket->forceFill([
                'last_message_at' => $message->created_at,
                // A platform reply moves an open ticket to "pending" (i.e.
                // waiting on the client) unless triage already resolved or
                // closed it in the same request.
                'status' => $ticket->status === PlatformSupportTicket::STATUS_OPEN
                    ? PlatformSupportTicket::STATUS_PENDING
                    : $ticket->status,
            ])->save();

            return $message;
        });
    }

    public function triage(
        PlatformSupportTicket $ticket,
        ?string $status,
        ?string $priority,
        ?int $assignedSuperAdminId,
    ): PlatformSupportTicket {
        $updates = [];

        if ($status !== null) {
            $updates['status'] = $status;
            $updates['resolved_at'] = in_array($status, [PlatformSupportTicket::STATUS_RESOLVED, PlatformSupportTicket::STATUS_CLOSED], true)
                ? ($ticket->resolved_at ?? now())
                : null;
        }

        if ($priority !== null) {
            $updates['priority'] = $priority;
        }

        if ($assignedSuperAdminId !== null) {
            $updates['assigned_super_admin_id'] = $assignedSuperAdminId;
        }

        if ($updates !== []) {
            $ticket->forceFill($updates)->save();
        }

        return $ticket->refresh();
    }
}
