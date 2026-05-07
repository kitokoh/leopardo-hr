<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AttendanceMonthlyReportTest extends TestCase
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

    public function test_manager_can_get_monthly_attendance_report_json_and_csv(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Kaci',
            'matricule' => 'EMP-001',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:30:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 18:30:00', 'UTC'),
            'hours_worked' => 9,
            'overtime_hours' => 1,
            'late_minutes' => 15,
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $json = $this->getJson('/api/v1/attendance/monthly-report?month=2026-05');
        $json->assertOk();
        $json->assertJsonPath('data.period.month', '2026-05');
        $json->assertJsonPath('data.totals.worked_hours', 9);
        $json->assertJsonPath('data.employees.1.name', 'Nadia Kaci');

        $csv = $this->get('/api/v1/attendance/monthly-report?month=2026-05&format=csv');
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv->assertSee('EMP-001');
    }

    public function test_employee_cannot_get_monthly_attendance_report(): void
    {
        $employee = Employee::factory()->create(['role' => 'employee']);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/attendance/monthly-report?month=2026-05')->assertStatus(403);
    }
}
