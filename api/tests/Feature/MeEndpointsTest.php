<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MeEndpointsTest extends TestCase
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

    public function test_employee_can_view_own_daily_summary_without_knowing_id(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-10 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-10 17:00:00', 'UTC'),
            'hours_worked' => 9.00,
            'overtime_hours' => 1.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/daily-summary?date=2026-04-10');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);
        $this->assertSame(9.0, (float) $response->json('data.hours_worked'));
        $this->assertSame(1.0, (float) $response->json('data.overtime_hours'));
    }

    public function test_employee_can_view_own_quick_estimate_without_knowing_id(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

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

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/quick-estimate?from=2026-04-01&to=2026-04-05');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);
        // gross = (8h*100) + (8h*100 + 2h*100*1.25) = 1850
        $response->assertJsonPath('data.totals.gross', 1850);
        $this->assertSame(2.0, (float) $response->json('data.totals.overtime_hours'));
        $response->assertJsonPath('data.totals.net', 1683.5);
    }

    public function test_employee_monthly_summary_defaults_to_current_month(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        $firstOfMonth = Carbon::now($company->timezone)->startOfMonth();

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $firstOfMonth->toDateString(),
            'session_number' => 1,
            'check_in' => $firstOfMonth->copy()->setTime(8, 0)->setTimezone('UTC'),
            'check_out' => $firstOfMonth->copy()->setTime(16, 0)->setTimezone('UTC'),
            'hours_worked' => 8.00,
            'overtime_hours' => 0.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/monthly-summary');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.year', (int) $firstOfMonth->format('Y'));
        $response->assertJsonPath('data.month', (int) $firstOfMonth->format('m'));
        $response->assertJsonStructure([
            'data' => [
                'period' => ['from', 'to', 'working_days', 'days_present'],
                'totals' => ['hours', 'overtime_hours', 'gross', 'deductions', 'net'],
            ],
        ]);
    }

    public function test_manager_can_also_use_me_endpoints_for_self(): void
    {
        $company = Company::query()->create([
            'name' => 'Company Z',
            'slug' => 'company-z',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'z@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Rita',
            'last_name' => 'M.',
            'email' => 'rita@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 120,
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'date' => '2026-04-10',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-10 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-10 17:00:00', 'UTC'),
            'hours_worked' => 9.00,
            'overtime_hours' => 1.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($manager);

        $managerDaily = $this->getJson('/api/v1/me/daily-summary?date=2026-04-10')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $manager->id);
        $this->assertSame(9.0, (float) $managerDaily->json('data.hours_worked'));

        $this->getJson('/api/v1/me/quick-estimate?from=2026-04-10&to=2026-04-10')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $manager->id);
    }

    public function test_me_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/me/daily-summary')->assertUnauthorized();
        $this->getJson('/api/v1/me/quick-estimate')->assertUnauthorized();
        $this->getJson('/api/v1/me/monthly-summary')->assertUnauthorized();
    }

    public function test_me_quick_estimate_validates_date_format(): void
    {
        [, $employee] = $this->seedCompanyAndEmployee();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/quick-estimate?from=not-a-date&to=2026-04-05')
            ->assertUnprocessable();

        $this->getJson('/api/v1/me/quick-estimate?from=2026-04-10&to=2026-04-01')
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedCompanyAndEmployee(): array
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
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);

        return [$company, $employee];
    }
}
