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

class QuickEstimateTest extends TestCase
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

    public function test_manager_can_get_quick_estimate_with_dz_deduction(): void
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
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-01',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-01 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-01 16:00:00', 'UTC'),
            'hours_worked' => 8.00,
            'overtime_hours' => 0.00,
            'status' => 'ontime',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-02',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-02 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-02 18:00:00', 'UTC'),
            'hours_worked' => 10.00,
            'overtime_hours' => 2.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/quick-estimate?from=2026-04-01&to=2026-04-05');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);

        // gross = (8h*100) + (8h*100 + 2h*100*1.25) = 800 + 1050 = 1850
        $response->assertJsonPath('data.totals.gross', 1850);
        // DZ deductions 9%
        $response->assertJsonPath('data.totals.deductions', 166.5);
        $response->assertJsonPath('data.totals.net', 1683.5);
    }

    public function test_employee_cannot_access_quick_estimate(): void
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
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/quick-estimate?from=2026-04-01&to=2026-04-05');
        $response->assertForbidden();
    }
}
