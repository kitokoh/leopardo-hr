<?php

namespace Tests\Feature\Absences;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AbsenceCancelTest extends TestCase
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

    public function test_employee_can_cancel_own_pending_absence(): void
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

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'reason' => 'Vacances familiales',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->deleteJson('/api/v1/absences/' . $absence->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_approved_absence_returns_422(): void
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

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'approved', // Already approved, cannot cancel
            'approved_by' => $manager->id,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->deleteJson('/api/v1/absences/' . $absence->id);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'ABSENCE_NOT_PENDING');
    }

    public function test_employee_cannot_cancel_other_employee_absence(): void
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

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employeeA@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employeeB@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Absence belongs to employee B
        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        // Employee A trying to cancel employee B's absence
        Sanctum::actingAs($employeeA);

        $response = $this->deleteJson('/api/v1/absences/' . $absence->id);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_cancel_employee_absence_via_destroy(): void
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

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Absence belongs to employee, not manager
        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        // Manager trying to cancel via destroy (reserved for employee owner only)
        Sanctum::actingAs($manager);

        $response = $this->deleteJson('/api/v1/absences/' . $absence->id);

        $response->assertStatus(403);
    }
}
