<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TodayAndHistoryTest extends TestCase
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

    public function test_today_endpoint_returns_self_status_for_employee(): void
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

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201);

        $today = $this->getJson('/api/v1/attendance/today');

        $today->assertOk();
        $today->assertJsonPath('data.employee_id', $employee->id);
        $today->assertJsonPath('data.checked_in', true);
        $today->assertJsonPath('data.check_in_time', '08:00');
    }

    public function test_employee_history_returns_only_self(): void
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

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'employee_id' => $employeeA->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);

        AttendanceLog::query()->create([
            'employee_id' => $employeeB->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employeeA);

        $resp = $this->getJson('/api/v1/attendance');

        $resp->assertOk();
        $ids = collect($resp->json('data'))->pluck('employee_id')->unique()->values()->all();
        $this->assertSame([$employeeA->id], $ids);
    }

    public function test_manager_can_view_today_for_all_employees_and_employee_cannot_view_others(): void
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        Sanctum::actingAs($manager);
        $all = $this->getJson('/api/v1/attendance/today');

        $all->assertOk();
        $all->assertJsonCount(2, 'data');

        Sanctum::actingAs($employee);
        $forbidden = $this->getJson("/api/v1/attendance/today?employee_id={$manager->id}");
        $forbidden->assertStatus(403);
    }

    public function test_tenant_isolation_on_attendance_history(): void
    {
        $companyA = Company::query()->create([
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

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $companyB);
        AttendanceLog::query()->create([
            'employee_id' => $employeeB->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($managerA);
        $resp = $this->getJson('/api/v1/attendance');

        $resp->assertOk();
        $this->assertSame([], $resp->json('data'));
    }
}
