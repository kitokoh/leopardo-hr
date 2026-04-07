<?php

namespace Tests\Unit;

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_check_in_throws_when_open_session_exists(): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule();
        app()->instance('current_company', $company);

        Carbon::setTestNow(CarbonImmutable::parse('2026-04-07 08:10:00', 'UTC'));

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => '2026-04-07',
            'session_number' => 1,
            'check_in' => now('UTC')->subMinutes(5),
            'method' => 'mobile',
            'status' => 'incomplete',
            'late_minutes' => 0,
        ]);

        $this->expectException(AlreadyCheckedInException::class);

        app(AttendanceService::class)->checkIn($employee);
    }

    public function test_check_out_throws_without_open_session(): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule();
        app()->instance('current_company', $company);
        Carbon::setTestNow(CarbonImmutable::parse('2026-04-07 17:30:00', 'UTC'));

        $this->expectException(MissingCheckInException::class);

        app(AttendanceService::class)->checkOut($employee);
    }

    public function test_check_out_calculates_hours_and_overtime(): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule();
        app()->instance('current_company', $company);
        Carbon::setTestNow(CarbonImmutable::parse('2026-04-07 18:30:00', 'UTC'));

        $log = AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => '2026-04-07',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-07 09:00:00', 'UTC'),
            'method' => 'mobile',
            'status' => 'incomplete',
            'late_minutes' => 0,
        ]);

        $result = app(AttendanceService::class)->checkOut($employee, 36.77, 3.05);

        $this->assertSame($log->id, $result->id);
        $this->assertSame('9.50', $result->fresh()->hours_worked);
        $this->assertSame('1.50', $result->fresh()->overtime_hours);
        $this->assertSame('36.77000000', $result->fresh()->gps_lat);
        $this->assertSame('3.05000000', $result->fresh()->gps_lng);
    }

    private function seedEmployeeWithSchedule(): array
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
            'currency' => 'DZD',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Jour',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8,
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

        return [$company, $employee];
    }
}
