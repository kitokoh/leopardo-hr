<?php

namespace Tests\Unit;

use App\Events\EmployeeCreated;
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
            \App\Events\EmployeeCreated::class,
            \App\Events\EmployeeArchived::class,
            \App\Events\AttendanceCheckedIn::class,
            \App\Events\AttendanceCheckedOut::class,
            \App\Events\AbsenceRequested::class,
            \App\Events\AbsenceApproved::class,
            \App\Events\AbsenceRejected::class,
            \App\Events\PayrollValidated::class,
        ];

        foreach ($events as $event) {
            Event::assertListening($event, AuditLogger::class);
            Event::assertListening($event, WebhookListener::class);
        }
    }
}
