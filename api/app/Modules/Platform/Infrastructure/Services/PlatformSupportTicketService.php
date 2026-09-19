<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Mail\SupportTicketOpenedMail;
use App\Mail\SupportTicketPlatformReplyMail;
use App\Mail\SupportTicketTenantReplyMail;
use App\Modules\Platform\Domain\Models\PlatformSupportMessage;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * PA2-COMM-012 — Pilot client support center.
 *
 * Centralizes the small set of state transitions around a support ticket
 * (open, reply, triage, resolve) so both the tenant-facing controller and
 * the platform admin controller share the same rules instead of duplicating
 * `last_message_at` bookkeeping and status guards.
 *
 * #7760 — each transition that adds a message also queues an email
 * notification to the other side of the conversation (new ticket → platform
 * support team; platform reply → ticket author; tenant reply → assigned
 * super-admin, falling back to the support team). Emails are queued AFTER
 * the transaction commits so a rollback never produces a ghost
 * notification.
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
        $ticket = DB::transaction(function () use ($author, $subject, $category, $body, $priority): PlatformSupportTicket {
            $now = now();

            /** @var PlatformSupportTicket $ticket */
            $ticket = new PlatformSupportTicket([
                'created_by_employee_id' => $author->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => PlatformSupportTicket::STATUS_OPEN,
                'last_message_at' => $now,
            ]);
            // #7711 : company_id n'est plus mass-assignable — valeur de
            // confiance issue de l'auteur authentifié, posée en forceFill.
            $ticket->forceFill(['company_id' => $author->company_id])->save();

            PlatformSupportMessage::query()->create([
                'platform_support_ticket_id' => $ticket->id,
                'author_employee_id' => $author->id,
                'body' => $body,
                'created_at' => $now,
            ]);

            return $ticket;
        });

        // #7760 — new ticket: notify every super-admin holding the platform
        // `support.manage` permission (the ticket has no assignee yet).
        foreach ($this->supportTeamRecipients() as $admin) {
            Mail::to($admin->email)->send(new SupportTicketOpenedMail($ticket, $ticket->company));
        }

        return $ticket;
    }

    public function replyAsEmployee(PlatformSupportTicket $ticket, Employee $author, string $body): PlatformSupportMessage
    {
        $message = DB::transaction(function () use ($ticket, $author, $body): PlatformSupportMessage {
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

        // #7760 — tenant reply: notify the assigned super-admin when the
        // ticket has one (and their account is still active), otherwise the
        // whole `support.manage` team.
        $assigned = $ticket->assignedSuperAdmin;
        $recipients = $assigned !== null && $assigned->isPlatformActive()
            ? [$assigned]
            : $this->supportTeamRecipients()->all();

        foreach ($recipients as $admin) {
            Mail::to($admin->email)->send(new SupportTicketTenantReplyMail($ticket, $ticket->company));
        }

        return $message;
    }

    public function replyAsSuperAdmin(PlatformSupportTicket $ticket, SuperAdmin $author, string $body): PlatformSupportMessage
    {
        $message = DB::transaction(function () use ($ticket, $author, $body): PlatformSupportMessage {
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

        // #7760 — platform reply: notify the tenant employee who opened the
        // ticket, in THEIR language (resolved by the mailable).
        $ticketAuthor = $ticket->createdBy;
        Mail::to($ticketAuthor->email)->send(new SupportTicketPlatformReplyMail($ticket, $ticketAuthor));

        return $message;
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

    /**
     * #7760 — internal recipients of support notifications: every ACTIVE
     * super-admin whose platform role carries the `support.manage`
     * permission (matrix in PlatformRole::permissions(), issue #7553). The
     * filter runs in PHP because the permission is derived from the role
     * enum, not stored per row — the super_admins table stays small (it is
     * the internal platform team).
     *
     * @return Collection<int, SuperAdmin>
     */
    private function supportTeamRecipients(): Collection
    {
        return SuperAdmin::query()
            ->get()
            ->filter(
                fn (SuperAdmin $admin): bool => $admin->isPlatformActive()
                    && $admin->hasPlatformPermission(PlatformPermission::SupportManage),
            )
            ->values();
    }
}
