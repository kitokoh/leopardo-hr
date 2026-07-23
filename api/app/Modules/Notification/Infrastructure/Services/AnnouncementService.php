<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PA2-COMM-004 — Publishes a manager-authored announcement to the targeted
 * audience (whole company, one department, or a single employee) by
 * fanning it out into per-employee in-app notifications through the
 * existing CommunicationService, so preferences/audit stay consistent
 * with every other notification in the product.
 *
 * PA2-COMM-011 — Moderation: an announcement can be created as a `draft`
 * (no fan-out, edited/published later), `scheduled` for a future
 * `scheduled_at` (fanned out by `announcements:publish-scheduled`), or
 * published immediately (unchanged default behaviour, still exercised by
 * every pre-existing `AnnouncementControllerTest` case). Draft/scheduled
 * announcements can be `cancel()`led before fan-out; every status
 * transition is written to `audit_logs` via `AuditLog::create()` (the same
 * ledger already used by `Auditable::recordAudit()` elsewhere) so
 * moderation stays traceable without duplicating the announcement history
 * on the row itself.
 */
class AnnouncementService
{
    public function __construct(
        private readonly CommunicationService $communicationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function publish(Employee $author, array $data): CompanyAnnouncement
    {
        /** @var string $status */
        $status = $data['status'] ?? CompanyAnnouncement::STATUS_PUBLISHED;
        $scheduledAt = $data['scheduled_at'] ?? null;

        // A `scheduled_at` in the future implicitly means "schedule this",
        // even if the caller didn't also pass status=scheduled explicitly.
        if ($status === CompanyAnnouncement::STATUS_PUBLISHED && $scheduledAt !== null) {
            $status = CompanyAnnouncement::STATUS_SCHEDULED;
        }

        $isImmediate = $status === CompanyAnnouncement::STATUS_PUBLISHED;

        $announcement = DB::transaction(function () use ($author, $data, $status, $scheduledAt, $isImmediate): CompanyAnnouncement {
            $announcement = CompanyAnnouncement::create([
                'company_id' => $author->company_id,
                'created_by' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'priority' => $data['priority'] ?? 'normal',
                'audience_type' => $data['audience_type'] ?? CompanyAnnouncement::AUDIENCE_COMPANY,
                'audience_department_id' => $data['audience_department_id'] ?? null,
                'audience_employee_id' => $data['audience_employee_id'] ?? null,
                'status' => $status,
                'published_at' => $isImmediate ? now() : null,
                'scheduled_at' => $status === CompanyAnnouncement::STATUS_SCHEDULED ? $scheduledAt : null,
                'expires_at' => $data['expires_at'] ?? null,
                'recipients_count' => 0,
            ]);

            if ($isImmediate) {
                $recipients = $this->resolveRecipients($announcement, $author);
                $announcement->update(['recipients_count' => $recipients->count()]);
            }

            $this->recordModerationEvent($announcement, $author, 'announcement_'.$status);

            return $announcement;
        });

        if ($isImmediate) {
            $this->dispatchToRecipients($announcement, $this->resolveRecipients($announcement, $author));
        }

        return $announcement;
    }

    /**
     * Publishes a still-draft/scheduled announcement now, fanning it out to
     * its resolved audience. Used both by the manual "publish now" action
     * on a draft and by the `announcements:publish-scheduled` command for
     * `scheduled` rows whose `scheduled_at` is due.
     */
    public function publishNow(CompanyAnnouncement $announcement): CompanyAnnouncement
    {
        if (! in_array($announcement->status, [CompanyAnnouncement::STATUS_DRAFT, CompanyAnnouncement::STATUS_SCHEDULED], true)) {
            throw new RuntimeException("Cannot publish announcement #{$announcement->id} from status '{$announcement->status}'.");
        }

        $author = $announcement->author ?? Employee::query()->find($announcement->created_by);
        if ($author === null) {
            // Author account deleted since draft/scheduling: cancel instead of
            // fanning out to a broken audience resolution.
            Log::warning('announcements:publish-scheduled skipped a row whose author no longer exists', [
                'announcement_id' => $announcement->id,
                'company_id' => $announcement->company_id,
            ]);

            return $announcement;
        }

        $announcement = DB::transaction(function () use ($announcement, $author): CompanyAnnouncement {
            $recipients = $this->resolveRecipients($announcement, $author);
            $announcement->update([
                'status' => CompanyAnnouncement::STATUS_PUBLISHED,
                'published_at' => now(),
                'recipients_count' => $recipients->count(),
            ]);

            $this->recordModerationEvent($announcement, $author, 'announcement_published');

            return $announcement;
        });

        $this->dispatchToRecipients($announcement, $this->resolveRecipients($announcement, $author));

        return $announcement;
    }

    /**
     * Withdraws a draft/scheduled announcement before it ever fans out.
     * Published announcements are not cancellable retroactively —
     * notifications already delivered cannot be un-sent; use `destroy()`
     * on the controller (author/RH/principal only) if it must be removed.
     */
    public function cancel(CompanyAnnouncement $announcement, Employee $actor): CompanyAnnouncement
    {
        if (! in_array($announcement->status, [CompanyAnnouncement::STATUS_DRAFT, CompanyAnnouncement::STATUS_SCHEDULED], true)) {
            throw new RuntimeException("Cannot cancel announcement #{$announcement->id} from status '{$announcement->status}'.");
        }

        DB::transaction(function () use ($announcement, $actor): void {
            $announcement->update([
                'status' => CompanyAnnouncement::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            $this->recordModerationEvent($announcement, $actor, 'announcement_cancelled');
        });

        return $announcement->refresh();
    }

    /**
     * Finds every `scheduled` announcement whose `scheduled_at` is now due,
     * across every tenant — the same cross-tenant scan pattern already used
     * by `AutoCloseAttendanceCommand`/`SocialPostRepository::findDuePosts()`
     * for scheduled background work.
     *
     * @return Collection<int, CompanyAnnouncement>
     */
    public function findDueScheduled(int $limit = 50): Collection
    {
        /** @var Collection<int, CompanyAnnouncement> */
        return CompanyAnnouncement::query()
            ->withoutGlobalScopes()
            ->where('status', CompanyAnnouncement::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    private function recordModerationEvent(CompanyAnnouncement $announcement, Employee $actor, string $action): void
    {
        AuditLog::create([
            'company_id' => $announcement->company_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => CompanyAnnouncement::class,
            'auditable_id' => $announcement->id,
            'new_values' => [
                'status' => $announcement->status,
                'scheduled_at' => $announcement->scheduled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return Collection<int, Employee>
     */
    public function resolveRecipients(CompanyAnnouncement $announcement, Employee $author): Collection
    {
        $query = Employee::query()
            ->where('company_id', $announcement->company_id)
            ->where('id', '!=', $author->id);

        match ($announcement->audience_type) {
            CompanyAnnouncement::AUDIENCE_DEPARTMENT => $query->where('department_id', $announcement->audience_department_id),
            CompanyAnnouncement::AUDIENCE_EMPLOYEE => $query->where('id', $announcement->audience_employee_id),
            default => null,
        };

        return $query->get();
    }

    /**
     * @param  Collection<int, Employee>  $recipients
     */
    private function dispatchToRecipients(CompanyAnnouncement $announcement, Collection $recipients): void
    {
        foreach ($recipients as $recipient) {
            $this->communicationService->notifyEmployee($recipient, 'company_announcement', [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'severity' => $announcement->priority,
                'source' => 'company_announcement',
            ], ['app']);
        }
    }
}
