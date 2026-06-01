<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AutoCloseAttendanceCommandTest extends TestCase
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

    public function test_auto_close_uses_tenant_policy_and_keeps_correction_context(): void
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
            'timezone' => 'Africa/Algiers',
            'metadata' => [
                'attendance_auto_close' => [
                    'enabled' => true,
                    'threshold_hours' => 10,
                    'workday_hours' => 8,
                    'overtime_margin_minutes' => 15,
                ],
            ],
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
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

        $checkIn = Carbon::parse('2026-05-31 06:00:00', 'UTC');
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-05-31',
            'session_number' => 1,
            'check_in' => $checkIn,
            'method' => 'mobile',
            'work_type' => 'normal',
            'status' => 'incomplete',
        ]);

        $this->travelTo(Carbon::parse('2026-05-31 20:00:00', 'UTC'));

        Artisan::call('attendance:auto-close', ['--threshold' => 12]);

        $log = AttendanceLog::query()->firstOrFail();

        $this->assertSame('2026-05-31 14:15:00', $log->check_out->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('8.25', $log->hours_worked);
        $this->assertSame('auto_close', $log->correction_note);
        $this->assertSame('ontime', $log->status);
        $this->assertTrue($log->punch_meta['auto_close']['correction_window']);
        $this->assertSame(10, $log->punch_meta['auto_close']['policy']['threshold_hours']);
    }
}
