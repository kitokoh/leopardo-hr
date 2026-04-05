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

class CheckOutTest extends TestCase
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

    public function test_cannot_check_out_without_check_in(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/attendance/check-out');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'MISSING_CHECK_IN');
    }

    public function test_employee_can_check_out_and_hours_are_calculated(): void
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

        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $checkIn = $this->postJson('/api/v1/attendance/check-in');
        $checkIn->assertStatus(201);

        $this->travelTo(Carbon::parse('2026-04-04 17:00:00', 'UTC'));
        $checkOut = $this->postJson('/api/v1/attendance/check-out');

        $checkOut->assertOk();
        $checkOut->assertJsonPath('data.hours_worked', '9.00');
        $checkOut->assertJsonPath('data.overtime_hours', '1.00');

        $log = AttendanceLog::query()->firstOrFail();
        $this->assertSame('9.00', $log->hours_worked);
        $this->assertSame('1.00', $log->overtime_hours);
        $this->assertSame('2026-04-04 17:00:00', $log->check_out->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }
}
