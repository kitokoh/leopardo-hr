<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CorrectionWorkflowTest extends TestCase
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

    public function test_manager_lists_and_applies_pending_attendance_corrections(): void
    {
        [$company, $schedule, $manager, $employee] = $this->fixture();

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:12:00', 'UTC'),
            'requested_check_out' => Carbon::parse('2026-05-27 17:20:00', 'UTC'),
            'reason' => 'Oubli du pointage mobile',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/attendance/corrections')
            ->assertOk()
            ->assertJsonPath('data.0.id', $correction->id)
            ->assertJsonPath('data.0.employee.name', 'Amina Test');

        $response = $this->putJson("/api/v1/attendance/corrections/{$correction->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('attendance_log.employee_id', $employee->id)
            ->assertJsonPath('attendance_log.method', 'manual');

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'status' => 'applied',
            'reviewed_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-05-27',
            'method' => 'manual',
            'corrected_by' => $manager->id,
        ]);
    }

    public function test_employee_cannot_list_correction_queue(): void
    {
        [, , , $employee] = $this->fixture();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/attendance/corrections')->assertForbidden();
    }

    public function test_manager_cannot_apply_correction_from_another_tenant(): void
    {
        [, , $manager] = $this->fixture('company-a', 'a.test');
        [$otherCompany, , , $otherEmployee] = $this->fixture('company-b', 'b.test');

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:00:00', 'UTC'),
            'reason' => 'Tenant etranger',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/attendance/corrections/{$correction->id}/approve")
            ->assertNotFound();
    }

    /**
     * @return array{Company, Schedule, Employee, Employee}
     */
    private function fixture(string $slug = 'company-a', string $domain = 'company.test'): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Nadia',
            'last_name' => 'Manager',
            'email' => 'manager@'.$domain,
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Amina',
            'last_name' => 'Test',
            'email' => 'employee@'.$domain,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-05-26',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-05-26 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-26 17:00:00', 'UTC'),
            'method' => 'mobile',
            'status' => 'ontime',
            'hours_worked' => 8.00,
            'overtime_hours' => 0.00,
            'late_minutes' => 0,
        ]);

        return [$company, $schedule, $manager, $employee];
    }
}
