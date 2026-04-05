<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class WebManagerPagesTest extends TestCase
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

    public function test_manager_can_open_dashboard(): void
    {
        [$company, $manager, $employee] = $this->makeCompanyWithUsers();

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now('UTC')->setTimezone($company->timezone)->toDateString(),
            'session_number' => 1,
            'check_in' => now('UTC')->subHours(8),
            'check_out' => now('UTC')->subHour(),
            'status' => 'ontime',
            'hours_worked' => 7,
            'overtime_hours' => 0,
        ]);

        $response = $this->actingAs($manager, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard manager');
        $response->assertSee($employee->email);
    }

    public function test_employee_is_forbidden_from_dashboard(): void
    {
        [, , $employee] = $this->makeCompanyWithUsers();

        $response = $this->actingAs($employee, 'web')->get('/dashboard');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_employee_pages(): void
    {
        [, , $employee] = $this->makeCompanyWithUsers();

        $detail = $this->get("/employees/{$employee->id}");
        $detail->assertRedirect('/login');

        $estimate = $this->get("/employees/{$employee->id}/quick-estimate?from=2026-04-04&to=2026-04-04");
        $estimate->assertRedirect('/login');

        $pdf = $this->get("/employees/{$employee->id}/receipt?from=2026-04-04&to=2026-04-04");
        $pdf->assertRedirect('/login');
    }

    public function test_manager_can_open_employee_detail_and_download_pdf(): void
    {
        [$company, $manager, $employee] = $this->makeCompanyWithUsers();

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-04',
            'session_number' => 1,
            'check_in' => '2026-04-04 08:00:00+00:00',
            'check_out' => '2026-04-04 17:00:00+00:00',
            'status' => 'ontime',
            'hours_worked' => 8,
            'overtime_hours' => 0,
        ]);

        $detail = $this->actingAs($manager, 'web')->get("/employees/{$employee->id}");
        $detail->assertOk();
        $detail->assertSee($employee->email);

        $estimate = $this->actingAs($manager, 'web')
            ->getJson("/employees/{$employee->id}/quick-estimate?from=2026-04-04&to=2026-04-04");
        $estimate->assertOk();
        $estimate->assertJsonPath('data.employee_id', $employee->id);

        $pdf = $this->actingAs($manager, 'web')
            ->get("/employees/{$employee->id}/receipt?from=2026-04-04&to=2026-04-04");
        $pdf->assertOk();
        $content = $pdf->getContent();
        $this->assertNotFalse($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_employee_is_forbidden_from_other_manager_pages(): void
    {
        [, , $employee] = $this->makeCompanyWithUsers();

        $detail = $this->actingAs($employee, 'web')->get("/employees/{$employee->id}");
        $detail->assertForbidden();

        $estimate = $this->actingAs($employee, 'web')
            ->get("/employees/{$employee->id}/quick-estimate?from=2026-04-04&to=2026-04-04");
        $estimate->assertForbidden();

        $pdf = $this->actingAs($employee, 'web')
            ->get("/employees/{$employee->id}/receipt?from=2026-04-04&to=2026-04-04");
        $pdf->assertForbidden();
    }

    public function test_manager_cannot_open_employee_from_other_company(): void
    {
        [$company, $manager] = $this->makeCompanyWithUsers();

        $otherCompany = Company::withoutGlobalScopes()->create([
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
            'currency' => 'DZD',
        ]);

        $outsider = Employee::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'daily',
            'salary_base' => 800,
        ]);

        $response = $this->actingAs($manager, 'web')->get("/employees/{$outsider->id}");

        $response->assertNotFound();
    }

    private function makeCompanyWithUsers(): array
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
            'first_name' => 'Manager',
            'last_name' => 'One',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
            'salary_type' => 'daily',
            'salary_base' => 800,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'daily',
            'salary_base' => 800,
        ]);

        return [$company, $manager, $employee];
    }
}
