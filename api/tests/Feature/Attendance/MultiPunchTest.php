<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MultiPunchTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_employee_can_create_multiple_sessions_in_one_day(): void
    {
        [$employee] = $this->employeeFixture();
        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in', ['work_type' => 'normal'])
            ->assertStatus(201)
            ->assertJsonPath('data.session_number', 1)
            ->assertJsonPath('data.work_type', 'normal');

        $this->travelTo(Carbon::parse('2026-04-04 12:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-out', ['work_type' => 'break'])
            ->assertOk()
            ->assertJsonPath('data.session_number', 1);

        $this->travelTo(Carbon::parse('2026-04-04 13:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in', [
            'work_type' => 'overtime',
            'punch_note' => 'Support client exceptionnel',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.session_number', 2)
            ->assertJsonPath('data.work_type', 'overtime');

        $this->travelTo(Carbon::parse('2026-04-04 15:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('data.session_number', 2)
            ->assertJsonPath('data.overtime_hours', '2.00');

        $logs = AttendanceLog::query()->orderBy('session_number')->get();

        $this->assertCount(2, $logs);
        $this->assertSame([1, 2], $logs->pluck('session_number')->all());
        $this->assertSame('overtime', $logs->last()->work_type);
        $this->assertSame('Support client exceptionnel', $logs->last()->punch_note);
    }

    public function test_today_returns_sessions_and_daily_summary(): void
    {
        [$employee] = $this->employeeFixture();
        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->travelTo(Carbon::parse('2026-04-04 12:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-out', ['work_type' => 'break'])->assertOk();
        $this->travelTo(Carbon::parse('2026-04-04 13:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in', ['work_type' => 'mission'])->assertStatus(201);

        $today = $this->getJson('/api/v1/attendance/today');

        $today->assertOk();
        $today->assertJsonCount(2, 'data.sessions');
        $today->assertJsonPath('data.summary.sessions_count', 2);
        $today->assertJsonPath('data.summary.is_working', true);
        $today->assertJsonPath('data.summary.current_work_type', 'mission');
        $today->assertJsonPath('data.summary.break_minutes', 60);
    }

    /**
     * @return array{0: Employee, 1: Company}
     */
    private function employeeFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [$employee, $company];
    }
}
