<?php

namespace Tests\Feature\Estimation;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
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

        $employee = new Employee([
            'email' => 'employee@a.test',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

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

        $employee = new Employee([
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@a.test',
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

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

        $employeeA = new Employee([
            'email' => 'a@company.test',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);
        $employeeA->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeA->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $employeeB = new Employee([
            'email' => 'b@company.test',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);
        $employeeB->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeB->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

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

        $manager = new Employee([
            'email' => 'manager@company.test',
            'salary_type' => 'fixed',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
            'salary_base' => 0,
        ])->save();

        $employee = new Employee([
            'email' => 'employee@company.test',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/daily-summary?date=2026-04-04');
        $response->assertOk();
    }
}

