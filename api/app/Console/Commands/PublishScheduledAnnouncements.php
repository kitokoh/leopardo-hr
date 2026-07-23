<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Infrastructure\Services\AnnouncementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PA2-COMM-011 — Company announcement moderation.
 *
 * Publishes every `scheduled` company announcement whose `scheduled_at` is
 * due, across every tenant — same cross-tenant scan pattern as
 * `AutoCloseAttendanceCommand` and `PublishScheduledSocialPosts`.
 * `AnnouncementService::publishNow()` already fans out to the resolved
 * audience and records the moderation audit event; this command only adds
 * the per-row exception guard so one broken row never stops the batch.
 */
class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled {--limit=50 : Maximum number of announcements to process per run}';

    protected $description = 'Publishes scheduled company announcements whose scheduled_at is due';

    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dueAnnouncements = $this->announcements->findDueScheduled($limit);

        $this->info("announcements:publish-scheduled — {$dueAnnouncements->count()} announcement(s) due.");

        $published = 0;
        $failed = 0;

        $dueAnnouncements->each(function (CompanyAnnouncement $announcement) use (&$published, &$failed): void {
            try {
                $this->announcements->publishNow($announcement);
                $published++;
            } catch (Throwable $e) {
                $failed++;

                Log::error('announcements:publish-scheduled — unexpected failure', [
                    'announcement_id' => $announcement->id,
                    'company_id' => $announcement->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->info("Published: {$published} — Failed: {$failed}.");

        Log::info('announcements:publish-scheduled run complete', [
            'due' => $dueAnnouncements->count(),
            'published' => $published,
            'failed' => $failed,
        ]);

        return self::SUCCESS;
    }
}
