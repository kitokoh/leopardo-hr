<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PA2-COMM-004 — Publishes a manager-authored announcement to the targeted
 * audience (whole company, one department, or a single employee) by
 * fanning it out into per-employee in-app notifications through the
 * existing CommunicationService, so preferences/audit stay consistent
 * with every other notification in the product.
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
        $announcement = DB::transaction(function () use ($author, $data): CompanyAnnouncement {
            $announcement = CompanyAnnouncement::create([
                'company_id' => $author->company_id,
                'created_by' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'priority' => $data['priority'] ?? 'normal',
                'audience_type' => $data['audience_type'] ?? CompanyAnnouncement::AUDIENCE_COMPANY,
                'audience_department_id' => $data['audience_department_id'] ?? null,
                'audience_employee_id' => $data['audience_employee_id'] ?? null,
                'published_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            $recipients = $this->resolveRecipients($announcement, $author);
            $announcement->update(['recipients_count' => $recipients->count()]);

            return $announcement;
        });

        $this->dispatchToRecipients($announcement, $this->resolveRecipients($announcement, $author));

        return $announcement;
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
