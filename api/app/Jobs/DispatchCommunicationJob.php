<?php

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCommunicationJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>|null  $channels
     */
    public function __construct(
        public int $employeeId,
        public ?string $companyId,
        public string $templateKey,
        public array $context = [],
        public ?array $channels = null,
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
        return [new EnsureTenantContext()];
    }

    public function handle(CommunicationService $communication): void
    {
        // Tenant context (search_path + current_company) is already active at
        // this point (when companyId is set) thanks to EnsureTenantContext.
        $employee = Employee::query()->find($this->employeeId);

        if (! ($employee instanceof Employee)) {
            return;
        }

        $communication->notifyEmployee($employee, $this->templateKey, $this->context, $this->channels);
    }
}

