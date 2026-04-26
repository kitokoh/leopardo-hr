<?php

namespace Tests\Feature\Absences;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalanceLog;
use App\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AbsenceStoreTest extends TestCase
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

    public function test_employee_can_create_absence(): void
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

        // Give employee sufficient leave balance
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'reason' => 'Vacances familiales',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.absence_type_id', $absenceType->id);
        $response->assertJsonPath('data.start_date', '2026-04-10');
        $response->assertJsonPath('data.end_date', '2026-04-12');
        $response->assertJsonPath('data.reason', 'Vacances familiales');

        $this->assertDatabaseHas('absences', [
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'status' => 'pending',
        ]);
    }

    public function test_days_count_calculated_automatically(): void
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

        // Give employee sufficient leave balance
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-14', // 5 days (10, 11, 12, 13, 14)
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.days_count', 5);

        $absence = Absence::query()->first();
        $this->assertSame(5, $absence->days_count);
    }

    public function test_insufficient_balance_returns_422(): void
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

        // Give employee insufficient leave balance (2 days)
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 2.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 2.0,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-14', // 5 days requested, but only 2 available
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INSUFFICIENT_LEAVE_BALANCE');
        $response->assertJsonStructure([
            'error' => [
                'code',
                'message',
            ],
        ]);
    }

    public function test_date_conflict_returns_422(): void
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

        // Give employee sufficient leave balance
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        // Create existing absence
        Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        // Try to create overlapping absence
        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-11', // Overlaps with existing absence
            'end_date' => '2026-04-13',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ABSENCE_DATE_CONFLICT');
    }

    public function test_end_date_before_start_date_returns_422(): void
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

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-15',
            'end_date' => '2026-04-10', // End date before start date
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors',
        ]);
    }

    public function test_missing_required_fields_returns_422(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            // Missing required fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'absence_type_id',
                'start_date',
                'end_date',
            ],
        ]);
    }

    public function test_non_deductible_type_ignores_balance(): void
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
            'name' => 'Congé maladie',
            'code' => 'CM',
            'is_paid' => true,
            'deducts_leave' => false, // Non-deductible type
            'requires_proof' => true,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Employee has 0 leave balance, but should still be able to create non-deductible absence
        // No LeaveBalanceLog created, so balance is 0

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-14', // 5 days
            'reason' => 'Maladie',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.days_count', 5);
    }
}