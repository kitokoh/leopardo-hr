<?php

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-ATT-004 - Self-service anomaly view: a plain employee (no manager
 * role) can see anomalies detected on their own attendance logs, but never
 * on a colleague's.
 */
class MyAttendanceAnomaliesTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_employee_can_view_their_own_attendance_anomalies(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $colleague = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        app()->instance('current_company', $company);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:35:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);

        // Colleague's own late arrival must never leak into this employee's
        // self-service anomaly view.
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $colleague->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 09:10:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 55,
            'status' => 'late',
        ]);

        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/attendance-anomalies?date_from=2026-05-06&date_to=2026-05-06');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 1);
        $response->assertJsonPath('data.summary.by_type.late_arrival', 1);
        $response->assertJsonPath('data.summary.business_impact.late_minutes', 20);
        $response->assertJsonCount(1, 'data.items');
    }

    public function test_employee_cannot_view_another_employees_anomalies_via_employee_id_override(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $colleague = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $colleague->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 09:10:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 55,
            'status' => 'late',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        // Attempting to pass another employee's id must be ignored: the
        // controller always force-overrides employee_id with the caller's
        // own id, so this must return zero anomalies for $employee.
        $response = $this->getJson('/api/v1/me/attendance-anomalies?employee_id='.$colleague->id.'&date_from=2026-05-06&date_to=2026-05-06');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 0);
        $response->assertJsonCount(0, 'data.items');
    }

    public function test_employee_with_no_anomalies_gets_an_empty_report(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/attendance-anomalies?date_from=2026-05-06&date_to=2026-05-06');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 0);
        $response->assertJsonCount(0, 'data.items');
    }
}
