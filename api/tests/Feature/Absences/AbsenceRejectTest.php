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

class AbsenceRejectTest extends TestCase
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

    public function test_manager_can_reject_pending_absence(): void
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
            'status' => 'pending',
            'reason' => 'Vacances familiales',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Période trop chargée pour l\'équipe',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'rejected');
        $response->assertJsonPath('data.rejected_reason', 'Période trop chargée pour l\'équipe');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'rejected',
            'rejected_reason' => 'Période trop chargée pour l\'équipe',
        ]);
    }

    public function test_reject_approved_absence_restores_balance(): void
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

        // Give employee initial leave balance
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'approved', // Already approved
            'approved_by' => $manager->id,
        ]);

        // Simulate balance deduction from approval
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => -3.0,
            'reason' => 'absence_approved',
            'reference_id' => $absence->id,
            'balance_after' => 17.0, // 20 - 3 = 17
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Changement de planning',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'rejected');

        // Check that balance was restored
        $this->assertDatabaseHas('leave_balance_logs', [
            'employee_id' => $employee->id,
            'delta' => 3.0, // Positive delta for restoration
            'reason' => 'absence_rejected',
            'reference_id' => $absence->id,
            'balance_after' => 20.0, // 17 + 3 = 20
        ]);
    }

    public function test_reject_without_reason_returns_422(): void
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
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            // Missing rejected_reason
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'rejected_reason',
            ],
        ]);
    }

    public function test_reject_cancelled_absence_returns_422(): void
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
            'status' => 'cancelled', // Cancelled absence
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Raison de rejet',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'ABSENCE_NOT_PENDING');
    }

    public function test_employee_cannot_reject(): void
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
            'role' => 'employee', // Regular employee, not manager
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
        ]);

        Sanctum::actingAs($employee);

        $response = $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Je veux rejeter ma propre absence',
        ]);

        $response->assertStatus(403);
    }
}
