<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeesRbacTest extends TestCase
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

    public function test_manager_can_list_employees_but_sees_only_company_scope(): void
    {
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
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('manager@a.test', $emails);
        $this->assertContains('employee@a.test', $emails);
        $this->assertNotContains('employee@b.test', $emails);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $companyA->id,
            'user_id' => $managerA->id,
            'action' => 'hr_data.employee_list_viewed',
        ]);
    }

    public function test_manager_employee_list_exposes_operational_work_states(): void
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
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $present = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'present@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $mission = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'mission@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $break = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'break@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $leave = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'leave@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $absent = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'absent@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $today = now()->toDateString();
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $present->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => now()->subHours(2),
            'work_type' => 'normal',
            'status' => 'incomplete',
        ]);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $mission->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => now()->subHour(),
            'work_type' => 'mission',
            'status' => 'incomplete',
        ]);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $break->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => now()->subHours(3),
            'work_type' => 'break',
            'status' => 'incomplete',
        ]);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $absent->id,
            'date' => $today,
            'session_number' => 1,
            'work_type' => 'normal',
            'status' => 'absent',
        ]);

        $absenceTypeId = DB::table('absence_types')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Conge annuel',
            'code' => 'annual',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
            'created_at' => now(),
        ]);
        Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $leave->id,
            'absence_type_id' => $absenceTypeId,
            'start_date' => $today,
            'end_date' => $today,
            'days_count' => 1,
            'status' => 'approved',
            'reason' => 'Conge valide',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees?per_page=20');

        $response->assertOk();
        $byEmail = collect($response->json('data'))->keyBy('email');

        $this->assertSame('present', $byEmail->get('present@a.test')['work_state']);
        $this->assertSame('mission', $byEmail->get('mission@a.test')['work_state']);
        $this->assertSame('break', $byEmail->get('break@a.test')['work_state']);
        $this->assertSame('leave', $byEmail->get('leave@a.test')['work_state']);
        $this->assertSame('absent', $byEmail->get('absent@a.test')['work_state']);
    }

    public function test_employee_cannot_list_employees(): void
    {
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
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employeeA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertStatus(403);
    }

    public function test_employee_can_view_self_but_not_others(): void
    {
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
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employeeA->createToken('tests')->plainTextToken;

        $self = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/employees/{$employeeA->id}");
        $self->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $companyA->id,
            'user_id' => $employeeA->id,
            'action' => 'hr_data.employee_profile_viewed',
            'auditable_type' => Employee::class,
            'auditable_id' => $employeeA->id,
        ]);

        $other = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/employees/{$managerA->id}");
        $other->assertStatus(403);
    }

    public function test_manager_can_archive_other_employee_but_not_self(): void
    {
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
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $employeeA->createToken('tests');
        $this->assertSame(1, $employeeA->tokens()->count());

        $token = $managerA->createToken('tests')->plainTextToken;

        $ok = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/employees/{$employeeA->id}/archive");
        $ok->assertOk();
        $ok->assertJsonPath('data.status', 'archived');
        $this->assertSame(0, $employeeA->fresh()->tokens()->count());

        $deny = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/employees/{$managerA->id}/archive");
        $deny->assertStatus(403);
    }

    public function test_manager_can_create_employee_and_company_id_is_injected(): void
    {
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
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@a.test',
                'password' => 'password123',
                'role' => 'employee',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', [
            'email' => 'john.doe@a.test',
            'company_id' => $companyA->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'email' => 'john.doe@a.test',
            'contract_start' => now()->startOfDay(),
        ]);
    }

    public function test_manager_cannot_create_employee_with_email_used_by_another_company(): void
    {
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
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'shared@tenant.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'first_name' => 'Leila',
                'last_name' => 'Ait',
                'email' => 'shared@tenant.test',
                'password' => 'password123',
                'role' => 'employee',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_manager_can_create_employee_with_matricule_used_by_another_company(): void
    {
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
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'matricule' => 'EMP-001',
            'email' => 'other@tenant.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'matricule' => 'EMP-001',
                'first_name' => 'Leila',
                'last_name' => 'Ait',
                'email' => 'matricule@tenant.test',
                'password' => 'password123',
                'role' => 'employee',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', [
            'company_id' => $companyA->id,
            'matricule' => 'EMP-001',
        ]);
    }

    public function test_manager_cannot_update_employee_to_duplicate_email_within_same_company(): void
    {
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
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee.one@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee.two@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employeeB->id}", [
                'email' => 'employee.one@a.test',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_manager_cannot_update_employee_to_duplicate_matricule_within_same_company(): void
    {
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
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $companyA->id,
            'matricule' => 'EMP-001',
            'email' => 'employee.one@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $companyA->id,
            'matricule' => 'EMP-002',
            'email' => 'employee.two@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employeeB->id}", [
                'matricule' => 'EMP-001',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['matricule']);
    }

    public function test_rh_manager_cannot_change_rh_role_from_employee_update(): void
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
        ]);

        $rh = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'rh@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);
        $targetRh = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'target-rh@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $rhToken = $rh->createToken('tests')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$rhToken}")
            ->patchJson("/api/v1/employees/{$targetRh->id}", [
                'role' => 'employee',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_principal_manager_can_revoke_rh_role_from_employee_update(): void
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
        ]);

        $principal = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'principal@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $targetRh = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'target-rh@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $principalToken = $principal->createToken('tests')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$principalToken}")
            ->patchJson("/api/v1/employees/{$targetRh->id}", [
                'role' => 'employee',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.manager_role', null);
    }

    public function test_employee_can_update_self_profile_but_role_is_ignored(): void
    {
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
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employeeA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employeeA->id}", [
                'first_name' => 'NewName',
                'role' => 'manager',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'id' => $employeeA->id,
            'first_name' => 'NewName',
            'role' => 'employee',
        ]);
    }
}
