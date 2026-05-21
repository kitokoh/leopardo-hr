<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Employee;
use App\Services\Communication\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCommunicationJob implements ShouldQueue
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

    public function handle(CommunicationService $communication): void
    {
        $company = $this->companyId ? Company::query()->find($this->companyId) : null;
        if ($company instanceof Company) {
            app()->instance('current_company', $company);
        }

        $employee = Employee::query()->find($this->employeeId);

        if (! ($employee instanceof Employee)) {
            app()->forgetInstance('current_company');

            return;
        }

        try {
            $communication->notifyEmployee($employee, $this->templateKey, $this->context, $this->channels);
        } finally {
            app()->forgetInstance('current_company');
        }
    }
}
