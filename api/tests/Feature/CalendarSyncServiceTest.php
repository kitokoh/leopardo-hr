<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Services\CalendarSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CalendarSyncServiceTest extends TestCase
{
    use CreatesMvpSchema;

    private CalendarSyncService $service;

    private Company $company;

    private Employee $employee;

    private AbsenceType $absenceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->service = new CalendarSyncService;
        $this->company = Company::factory()->create();
        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->absenceType = AbsenceType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Paid leave',
            'code' => 'PAID_LEAVE',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_connect_updates_existing_provider_and_disconnect_revokes_tokens(): void
    {
        $first = $this->service->connect(
            $this->employee,
            'google',
            'first-token',
            'first-refresh',
            'primary',
            now()->addHour()
        );

        $second = $this->service->connect(
            $this->employee,
            'google',
            'second-token',
            null,
            'work',
            now()->addHours(2)
        );

        $this->assertTrue($first->is($second));
        $this->assertSame('second-token', decrypt($second->refresh()->access_token));
        $this->assertNull($second->refresh()->refresh_token);
        $this->assertSame('work', $second->refresh()->calendar_id);

        $this->assertTrue($this->service->disconnect($this->employee, 'google'));

        $connection = $second->refresh();
        $this->assertFalse($connection->is_active);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
    }

    public function test_sync_leaves_pushes_google_events_and_stores_external_id(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'google-event-1'], 200),
        ]);

        $this->service->connect($this->employee, 'google', 'google-token', calendarId: 'primary');
        Absence::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Annual leave',
        ]);

        $this->assertSame(1, $this->service->syncLeaves($this->employee));

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertSame('absence', $event->source_type);
        $this->assertSame('google-event-1', $event->external_event_id);
        $this->assertSame('synced', $event->sync_status);
        $this->assertTrue($event->all_day);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/calendars/primary/events')
            && $request['description'] === 'Annual leave'
            && isset($request['start']['date'], $request['end']['date']));
    }

    public function test_sync_leaves_updates_existing_google_event_with_put(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response(['id' => 'existing-google-event'], 200),
        ]);

        $connection = $this->service->connect($this->employee, 'google', 'google-token');
        $absence = Absence::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => now()->addDays(5)->toDateString(),
        ]);

        CalendarEvent::query()->create([
            'employee_id' => $this->employee->id,
            'source_type' => 'absence',
            'source_id' => $absence->id,
            'provider' => $connection->provider,
            'external_event_id' => 'existing-google-event',
            'title' => 'Old title',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
            'all_day' => true,
            'sync_status' => 'pending',
        ]);

        $this->assertSame(1, $this->service->syncLeaves($this->employee));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/events/existing-google-event'));
    }

    public function test_sync_training_pushes_outlook_event(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/me/events' => Http::response(['id' => 'outlook-event-1'], 201),
        ]);

        $this->service->connect($this->employee, 'outlook', 'outlook-token');
        $this->createTrainingEnrollment();

        $this->assertSame(1, $this->service->syncTraining($this->employee));

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertSame('training_session', $event->source_type);
        $this->assertSame('outlook-event-1', $event->external_event_id);
        $this->assertFalse($event->all_day);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://graph.microsoft.com/v1.0/me/events'
            && $request['subject'] === 'Formation : Session'
            && $request['isAllDay'] === false);
    }

    public function test_sync_training_updates_existing_outlook_event_with_patch(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/me/events/*' => Http::response(['id' => 'existing-outlook-event'], 200),
        ]);

        $this->service->connect($this->employee, 'outlook', 'outlook-token');
        $sessionId = $this->createTrainingEnrollment();

        CalendarEvent::query()->create([
            'employee_id' => $this->employee->id,
            'source_type' => 'training_session',
            'source_id' => $sessionId,
            'provider' => 'outlook',
            'external_event_id' => 'existing-outlook-event',
            'title' => 'Existing training',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(5),
            'all_day' => false,
            'sync_status' => 'pending',
        ]);

        $this->assertSame(1, $this->service->syncTraining($this->employee));

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/events/existing-outlook-event'));
    }

    public function test_caldav_connection_marks_events_synced_without_external_http(): void
    {
        Http::fake();

        $this->service->connect($this->employee, 'caldav', 'local-token');
        Absence::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(1, $this->service->syncLeaves($this->employee));
        $this->assertSame('synced', CalendarEvent::query()->firstOrFail()->sync_status);
        Http::assertNothingSent();
    }

    public function test_provider_error_marks_event_failed_and_does_not_count_sync(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->service->connect($this->employee, 'google', 'google-token');
        Absence::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->assertSame(0, $this->service->syncLeaves($this->employee));
        $this->assertSame('pending', CalendarEvent::query()->firstOrFail()->sync_status);
    }

    public function test_get_events_returns_requested_range_in_chronological_order(): void
    {
        CalendarEvent::query()->create([
            'employee_id' => $this->employee->id,
            'provider' => 'caldav',
            'title' => 'Later',
            'starts_at' => '2026-06-03 08:00:00',
            'ends_at' => '2026-06-03 09:00:00',
            'all_day' => false,
            'source_type' => 'manual',
            'source_id' => 2,
            'sync_status' => 'synced',
        ]);

        CalendarEvent::query()->create([
            'employee_id' => $this->employee->id,
            'provider' => 'caldav',
            'title' => 'Earlier',
            'starts_at' => '2026-06-01 08:00:00',
            'ends_at' => '2026-06-01 09:00:00',
            'all_day' => false,
            'source_type' => 'manual',
            'source_id' => 1,
            'sync_status' => 'synced',
        ]);

        CalendarEvent::query()->create([
            'employee_id' => $this->employee->id,
            'provider' => 'caldav',
            'title' => 'Outside',
            'starts_at' => '2026-07-01 08:00:00',
            'ends_at' => '2026-07-01 09:00:00',
            'all_day' => false,
            'source_type' => 'manual',
            'source_id' => 3,
            'sync_status' => 'synced',
        ]);

        $events = $this->service->getEvents($this->employee, '2026-06-01', '2026-06-30');

        $this->assertSame(['Earlier', 'Later'], $events->pluck('title')->all());
    }

    private function createTrainingEnrollment(): int
    {
        $courseId = DB::table('training_courses')->insertGetId([
            'company_id' => $this->company->id,
            'title' => 'Security basics',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = DB::table('training_sessions')->insertGetId([
            'training_course_id' => $courseId,
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('training_enrollments')->insert([
            'training_session_id' => $sessionId,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $sessionId;
    }
}
