<?php

namespace Tests\Unit;

use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\PayrollValidated;
use App\Listeners\AuditLogger;
use App\Listeners\WebhookListener;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuditLoggerListenerTest extends TestCase
{
    public function test_employee_created_event_has_audit_logger_listener(): void
    {
        Event::fake();

        Event::assertListening(EmployeeCreated::class, AuditLogger::class);
    }

    public function test_employee_created_event_has_webhook_listener(): void
    {
        Event::fake();

        Event::assertListening(EmployeeCreated::class, WebhookListener::class);
    }

    public function test_all_domain_events_have_listeners(): void
    {
        Event::fake();

        $events = [
            EmployeeCreated::class,
            EmployeeArchived::class,
            AttendanceCheckedIn::class,
            AttendanceCheckedOut::class,
            AbsenceRequested::class,
            AbsenceApproved::class,
            AbsenceRejected::class,
            PayrollValidated::class,
        ];

        foreach ($events as $event) {
            Event::assertListening($event, AuditLogger::class);
            Event::assertListening($event, WebhookListener::class);
        }
    }
}
