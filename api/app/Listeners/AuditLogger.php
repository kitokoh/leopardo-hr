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
use App\Events\EmployeeRoleAssigned;
use App\Events\PayrollValidated;
use App\Core\Auth\Domain\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AuditLogger implements ShouldQueue
{
    public string $queue = 'audit';

    /** @var array<class-string, array{action: string, model: string}> */
    private const EVENT_MAP = [
        EmployeeCreated::class => ['action' => 'created', 'model' => 'employee'],
        EmployeeArchived::class => ['action' => 'archived', 'model' => 'employee'],
        AttendanceCheckedIn::class => ['action' => 'checked_in', 'model' => 'log'],
        AttendanceCheckedOut::class => ['action' => 'checked_out', 'model' => 'log'],
        AbsenceRequested::class => ['action' => 'requested', 'model' => 'absence'],
        AbsenceApproved::class => ['action' => 'approved', 'model' => 'absence'],
        AbsenceRejected::class => ['action' => 'rejected', 'model' => 'absence'],
        PayrollValidated::class => ['action' => 'validated', 'model' => 'payroll'],
    ];

    public function handle(object $event): void
    {
        if ($event instanceof EmployeeRoleAssigned) {
            $this->handleRoleAssigned($event);

            return;
        }

        $class = $event::class;
        $mapping = self::EVENT_MAP[$class] ?? null;

        if ($mapping === null) {
            return;
        }

        $model = $event->{$mapping['model']} ?? null;

        if ($model === null) {
            Log::warning('AuditLogger: missing model property', ['event' => $class]);

            return;
        }

        $companyId = $model->company_id ?? null;
        $userId = $this->resolveUserId($event);

        try {
            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'action' => $mapping['action'],
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'old_values' => null,
                'new_values' => $model->toArray(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditLogger: failed to write audit log', [
                'event' => $class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * PA2-MOB-007 — nominate/revoke RH permissions must leave an audit
     * trail with both the previous and new manager_role, and the
     * identity of the principal manager who made the change.
     */
    private function handleRoleAssigned(EmployeeRoleAssigned $event): void
    {
        try {
            AuditLog::create([
                'company_id' => $event->employee->company_id,
                'user_id' => $event->actor->id,
                'action' => $event->newRole ? 'role_assigned' : 'role_revoked',
                'auditable_type' => $event->employee->getMorphClass(),
                'auditable_id' => $event->employee->getKey(),
                'old_values' => ['manager_role' => $event->previousRole],
                'new_values' => ['manager_role' => $event->newRole],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent() ? mb_substr((string) request()?->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditLogger: failed to write role assignment audit log', [
                'employee_id' => $event->employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveUserId(object $event): ?int
    {
        if (property_exists($event, 'approver')) {
            return $event->approver->id;
        }

        $model = $event->{array_values(self::EVENT_MAP[$event::class] ?? [])[1] ?? ''} ?? null;

        if ($model === null) {
            return null;
        }

        return $model->employee_id ?? $model->user_id ?? $model->validated_by ?? null;
    }

    /** @return array<int, string> */
    public function subscribe(): array
    {
        return array_keys(self::EVENT_MAP);
    }
}

