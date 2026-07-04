<?php

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private ?string $resolvedCompanyId = null;

    public function __construct(
        public int $employeeId,
        public string $title,
        public string $body,
        public array $metadata = [],
    ) {
        $this->onQueue((string) config('communication.queue', 'notifications'));
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->withoutGlobalScopes()->find($this->employeeId);

        return $this->resolvedCompanyId = $employee?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(PushNotificationService $pushService): void
    {
        $employee = Employee::find($this->employeeId);

        if ($employee) {
            $pushService->sendToEmployee($employee, $this->title, $this->body, $this->metadata);
        }
    }
}
