<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AttendanceAnomaliesTest extends TestCase
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

    public function test_manager_can_view_attendance_anomaly_summary(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Samir']);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Nadia']);

        app()->instance('current_company', $company);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:35:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-07',
            'check_in' => Carbon::parse('2026-05-07 08:00:00', 'UTC'),
            'check_out' => null,
            'status' => 'incomplete',
        ]);

        AttendanceLog::factory()->manual()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-08',
            'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
            'corrected_by' => $manager->id,
        ]);

        AttendanceLog::factory()->withOvertime(4.5)->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-09',
            'check_in' => Carbon::parse('2026-05-09 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-09 21:30:00', 'UTC'),
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-10',
            'check_in' => Carbon::parse('2026-05-10 08:00:00', 'UTC'),
            'source_device_code' => 'KIOSK-01',
            'method' => 'biometric',
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-10',
            'check_in' => Carbon::parse('2026-05-10 08:00:03', 'UTC'),
            'source_device_code' => 'KIOSK-01',
            'method' => 'biometric',
        ]);

        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-10');

        $response->assertOk();
        $response->assertJsonPath('data.period.date_from', '2026-05-06');
        $response->assertJsonPath('data.period.date_to', '2026-05-10');
        $response->assertJsonPath('data.summary.total', 8);
        $response->assertJsonPath('data.summary.by_type.late_arrival', 1);
        $response->assertJsonPath('data.summary.by_type.missing_check_out', 1);
        $response->assertJsonPath('data.summary.by_type.manual_correction', 1);
        $response->assertJsonPath('data.summary.by_type.excessive_overtime', 1);
        $response->assertJsonPath('data.summary.by_type.rapid_device_sequence', 1);
        $response->assertJsonPath('data.summary.by_type.repeated_exact_check_in', 3);
    }

    public function test_employee_cannot_view_attendance_anomalies(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/attendance/anomalies')->assertStatus(403);
    }

    public function test_attendance_anomalies_are_scoped_to_manager_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        app()->instance('current_company', $companyB);
        AttendanceLog::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:35:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 60,
            'status' => 'late',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-06');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 0);
        $response->assertJsonCount(0, 'data.items');
    }

    public function test_attendance_anomalies_include_geofence_and_repeated_exact_check_ins(): void
    {
        $company = Company::factory()->create([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);
        foreach (['2026-05-06', '2026-05-07', '2026-05-08'] as $date) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'check_in' => Carbon::parse($date.' 08:00:00', 'UTC'),
                'check_out' => Carbon::parse($date.' 17:00:00', 'UTC'),
            ]);
        }

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-09',
            'check_in' => Carbon::parse('2026-05-09 08:30:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-09 17:00:00', 'UTC'),
            'gps_lat' => 36.9000,
            'gps_lng' => 3.2000,
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-09');

        $response->assertOk();
        $response->assertJsonPath('data.summary.by_type.repeated_exact_check_in', 3);
        $response->assertJsonPath('data.summary.by_type.out_of_geofence', 1);
    }
}
