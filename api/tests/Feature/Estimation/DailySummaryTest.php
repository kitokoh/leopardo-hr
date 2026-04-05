<?php

namespace Tests\Feature\Estimation;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DailySummaryTest extends TestCase
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

    public function test_returns_zero_for_absent_day(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/daily-summary?date=2026-04-04');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'absent');
        $response->assertJsonPath('data.total_estimated', 0);
        $response->assertJsonPath('data.currency', 'DZD');
    }

    public function test_returns_daily_summary_with_overtime(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-04',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-04 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-04 18:00:00', 'UTC'),
            'hours_worked' => 10.00,
            'overtime_hours' => 2.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/daily-summary?date=2026-04-04');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.name', 'Ahmed Benali');
        $response->assertJsonPath('data.status', 'complete');
        $response->assertJsonPath('data.base_gain', 800);
        $response->assertJsonPath('data.overtime_gain', 250);
        $response->assertJsonPath('data.total_estimated', 1050);
    }

    public function test_employee_cannot_view_other_employee_summary(): void
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

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'a@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'b@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        Sanctum::actingAs($employeeA);

        $response = $this->getJson('/api/v1/employees/'.$employeeB->id.'/daily-summary?date=2026-04-04');
        $response->assertForbidden();
    }

    public function test_manager_can_view_any_employee_summary(): void
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 0,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/daily-summary?date=2026-04-04');
        $response->assertOk();
    }
}
