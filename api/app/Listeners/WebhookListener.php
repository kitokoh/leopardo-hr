<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\PayrollValidated;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class WebhookListener implements ShouldQueue
{
    public string $queue = 'webhooks';

    /** @var array<class-string, string> */
    private const EVENT_NAMES = [
        EmployeeCreated::class => 'employee.created',
        EmployeeArchived::class => 'employee.archived',
        AttendanceCheckedIn::class => 'attendance.checked_in',
        AttendanceCheckedOut::class => 'attendance.checked_out',
        AbsenceRequested::class => 'absence.requested',
        AbsenceApproved::class => 'absence.approved',
        AbsenceRejected::class => 'absence.rejected',
        PayrollValidated::class => 'payroll.validated',
    ];

    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(object $event): void
    {
        $eventName = self::EVENT_NAMES[$event::class] ?? null;

        if ($eventName === null) {
            return;
        }

        $model = $this->resolveModel($event);

        if ($model === null) {
            return;
        }

        $companyId = $model->company_id ?? null;

        if ($companyId === null) {
            return;
        }

        try {
            $this->dispatcher->dispatch($companyId, $eventName, $model->toArray());
        } catch (\Throwable $e) {
            Log::error('WebhookListener: failed to dispatch webhook', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveModel(object $event): ?object
    {
        foreach (['employee', 'log', 'absence', 'payroll'] as $prop) {
            if (property_exists($event, $prop)) {
                return $event->{$prop};
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function subscribe(): array
    {
        return array_keys(self::EVENT_NAMES);
    }
}
