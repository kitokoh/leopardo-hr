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

class CheckInTest extends TestCase
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

    public function test_employee_can_check_in_and_uses_server_time(): void
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

        $response = $this->postJson('/api/v1/attendance/check-in', [
            'client_time' => '1999-01-01 00:00:00',
            'gps_lat' => 36.7538,
            'gps_lng' => 3.0588,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'ontime');

        $log = AttendanceLog::query()->firstOrFail();

        $this->assertSame($company->id, $log->company_id);
        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame('2026-04-04', $log->date->format('Y-m-d'));
        $this->assertSame('2026-04-04 08:00:00', $log->check_in->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('36.75380000', $log->gps_lat);
        $this->assertSame('3.05880000', $log->gps_lng);
    }

    public function test_employee_cannot_check_in_twice_without_check_out(): void
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

        $ok = $this->postJson('/api/v1/attendance/check-in');
        $ok->assertStatus(201);

        $dup = $this->postJson('/api/v1/attendance/check-in');
        $dup->assertStatus(409);
        $dup->assertJsonPath('message', 'ALREADY_CHECKED_IN');
    }

    public function test_late_status_when_check_in_after_tolerance(): void
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

        $this->travelTo(Carbon::parse('2026-04-04 08:20:00', 'UTC'));

        $response = $this->postJson('/api/v1/attendance/check-in');
        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'late');
    }
}
