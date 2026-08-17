<?php

namespace Tests\Feature\Absences;

use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class AbsenceShowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_can_view_own_absence(): void
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
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
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

        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employee@a.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

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

        $response = $this->getJson('/api/v1/absences/'.$absence->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $absence->id);
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.absence_type_id', $absenceType->id);
        $response->assertJsonPath('data.start_date', '2026-04-10');
        $response->assertJsonPath('data.end_date', '2026-04-12');
        $response->assertJsonPath('data.days_count', 3);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reason', 'Vacances familiales');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'employee_id',
                'absence_type_id',
                'start_date',
                'end_date',
                'days_count',
                'status',
                'reason',
                'approved_by',
                'rejected_reason',
                'created_at',
                'updated_at',
                'absenceType' => [
                    'id',
                    'name',
                    'code',
                    'deducts_leave',
                ],
            ],
        ]);
    }

    public function test_employee_cannot_view_other_employee_absence(): void
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
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
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

        $employeeA = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employeeA@a.test',
        ]);
        $employeeA->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeA->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $employeeB = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employeeB@a.test',
        ]);
        $employeeB->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeB->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id, // Absence belongs to employee B
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employeeA); // Employee A trying to view employee B's absence

        $response = $this->getJson('/api/v1/absences/'.$absence->id);

        $response->assertStatus(403);
    }

    public function test_manager_can_view_any_absence(): void
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
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
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

        $manager = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'manager@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employee@a.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'reason' => 'Vacances',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/absences/'.$absence->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $absence->id);
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.reason', 'Vacances');
    }

    public function test_absence_not_found_returns_404(): void
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
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
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

        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employee@a.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/absences/99999'); // Non-existent ID

        $response->assertStatus(404);
    }

    public function test_cross_company_absence_returns_404(): void
    {
        // Company A
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        // Company B
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $scheduleA = Schedule::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $scheduleB = Schedule::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $absenceTypeA = AbsenceType::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $absenceTypeB = AbsenceType::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Congé payé',
            // absence_types.code est globalement unique (fix passe 3) — code
            // distinct pour la société B afin de lever la contrainte.
            'code' => 'CPB',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $employeeA = new Employee([
            'schedule_id' => $scheduleA->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employee@a.test',
        ]);
        $employeeA->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeA->forceFill([
            'company_id' => $companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $employeeB = new Employee([
            'schedule_id' => $scheduleB->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'employee@b.test',
        ]);
        $employeeB->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employeeB->forceFill([
            'company_id' => $companyB->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        // Create absence in Company B
        $absenceB = Absence::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'absence_type_id' => $absenceTypeB->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        // Employee from Company A trying to access absence from Company B
        Sanctum::actingAs($employeeA);

        $response = $this->getJson('/api/v1/absences/'.$absenceB->id);

        $response->assertStatus(404); // Should return 404 to prevent information leakage
    }
}

