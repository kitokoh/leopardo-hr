<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\User;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendBulkNotificationsJob implements ShouldQueue, TenantScopedJob
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
        private readonly string $companyId,
    ) {
        $this->onQueue('notifications');
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
        return [new EnsureTenantContext()];
    }

    public function handle(): void
    {
        Log::channel('structured')->info('notifications.bulk.start', [
            'company_id' => $this->companyId,
            'count' => count($this->userIds),
            'type' => $this->notificationClass,
        ]);

        // `users` has no `company_id` column of its own (it is a platform-level
        // table living in the `public` schema) — the tenant link lives on
        // `user_employee_links.company_id`. The previous `User::where('company_id', ...)`
        // filter referenced a column that does not exist on this table and would
        // have thrown a QueryException as soon as it ran against a real DB.
        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->whereHas('employeeLinks', function (Builder $query): void {
                $query->where('company_id', $this->companyId);
            })
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
