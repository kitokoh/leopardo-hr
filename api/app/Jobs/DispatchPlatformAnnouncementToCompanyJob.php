<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Platform\Domain\Models\PlatformAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PA2-COMM-005 — Fans a platform-wide announcement out into per-employee
 * in-app notifications for one tenant company.
 *
 * A `PlatformAnnouncement` lives in the `public` schema (it is not owned by
 * any tenant), but its recipients — the company's employees — live in that
 * tenant's schema/rows. One job per company keeps each fan-out tenant-scoped
 * (via `EnsureTenantContext`, matching every other queued job that touches
 * tenant data) and lets a single company's failure retry independently
 * without blocking or repeating delivery to every other company.
 */
class DispatchPlatformAnnouncementToCompanyJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $platformAnnouncementId,
        public readonly string $companyId,
    ) {
        $this->onQueue((string) config('communication.queue', 'notifications'));
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(CommunicationService $communicationService): void
    {
        $announcement = PlatformAnnouncement::query()->find($this->platformAnnouncementId);

        if (! $announcement instanceof PlatformAnnouncement) {
            Log::channel('structured')->warning('platform_announcement.dispatch.not_found', [
                'platform_announcement_id' => $this->platformAnnouncementId,
                'company_id' => $this->companyId,
            ]);

            return;
        }

        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->where('status', 'active')
            ->get();

        foreach ($employees as $employee) {
            $communicationService->notifyEmployee($employee, 'platform_announcement', [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'severity' => $announcement->severity,
                'source' => 'platform_announcement',
                'category' => 'platform',
            ], ['app']);
        }

        if ($employees->isNotEmpty()) {
            PlatformAnnouncement::query()
                ->where('id', $this->platformAnnouncementId)
                ->increment('recipients_count', $employees->count());
        }

        Log::channel('structured')->info('platform_announcement.dispatch.complete', [
            'platform_announcement_id' => $this->platformAnnouncementId,
            'company_id' => $this->companyId,
            'recipients' => $employees->count(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            "platform_announcement:{$this->platformAnnouncementId}",
            "company:{$this->companyId}",
        ];
    }
}
