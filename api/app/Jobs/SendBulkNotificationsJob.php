<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendBulkNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly array $userIds,
        private readonly string $notificationClass,
        private readonly array $notificationData,
        private readonly int $companyId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::channel('structured')->info('notifications.bulk.start', [
            'company_id' => $this->companyId,
            'count' => count($this->userIds),
            'type' => $this->notificationClass,
        ]);

        $users = \App\Models\User::whereIn('id', $this->userIds)
            ->where('company_id', $this->companyId)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $notification = new $this->notificationClass(...$this->notificationData);
        Notification::send($users, $notification);

        Log::channel('structured')->info('notifications.bulk.complete', [
            'company_id' => $this->companyId,
            'sent' => $users->count(),
        ]);
    }

    public function tags(): array
    {
        return [
            "company:{$this->companyId}",
            "notification:{$this->notificationClass}",
        ];
    }
}
