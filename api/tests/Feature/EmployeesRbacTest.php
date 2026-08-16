<?php

namespace Tests\Feature;

use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        $employeeA = Employee::query()->create([
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $manager->company_id = $company->id;
        $manager->role = 'manager';
        $manager->status = 'active';
        $manager->save();


        $present = Employee::query()->create([
            'email' => 'present@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $present->company_id = $company->id;
        $present->role = 'employee';
        $present->status = 'active';
        $present->save();

        $mission = Employee::query()->create([
            'email' => 'mission@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $mission->company_id = $company->id;
        $mission->role = 'employee';
        $mission->status = 'active';
        $mission->save();

        $break = Employee::query()->create([
            'email' => 'break@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $break->company_id = $company->id;
        $break->role = 'employee';
        $break->status = 'active';
        $break->save();

        $leave = Employee::query()->create([
            'email' => 'leave@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $leave->company_id = $company->id;
        $leave->role = 'employee';
        $leave->status = 'active';
        $leave->save();

        $absent = Employee::query()->create([
            'email' => 'absent@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $absent->company_id = $company->id;
        $absent->role = 'employee';
        $absent->status = 'active';
        $absent->save();


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
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        $employeeA = Employee::query()->create([
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        $employeeA = Employee::query()->create([
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();

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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


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

    public function test_principal_manager_can_create_employee_with_marketing_manager_role(): void
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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        $token = $managerA->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'first_name' => 'Sara',
                'last_name' => 'Comm',
                'email' => 'sara.marketing@a.test',
                'password' => 'password123',
                'role' => 'manager',
                'manager_role' => 'marketing',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.manager_role', 'marketing');
        $this->assertDatabaseHas('employees', [
            'email' => 'sara.marketing@a.test',
            'company_id' => $companyA->id,
            'manager_role' => 'marketing',
        ]);

        $marketingManager = Employee::query()->where('email', 'sara.marketing@a.test')->first();
        $this->assertTrue($marketingManager->isMarketing());
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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        $employeeA = Employee::query()->create([
            'email' => 'employee.one@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();


        $employeeB = Employee::query()->create([
            'email' => 'employee.two@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeB->company_id = $companyA->id;
        $employeeB->role = 'employee';
        $employeeB->status = 'active';
        $employeeB->save();


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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $managerA->company_id = $companyA->id;
        $managerA->role = 'manager';
        $managerA->status = 'active';
        $managerA->save();


        Employee::query()->create([
            'matricule' => 'EMP-001',
            'email' => 'employee.one@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee->company_id = $companyA->id;
        $employee->role = 'employee';
        $employee->status = 'active';
        $employee->save();


        $employeeB = Employee::query()->create([
            'matricule' => 'EMP-002',
            'email' => 'employee.two@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeB->company_id = $companyA->id;
        $employeeB->role = 'employee';
        $employeeB->status = 'active';
        $employeeB->save();


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
            'email' => 'rh@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'rh',
        ]);
        $rh->company_id = $company->id;
        $rh->role = 'manager';
        $rh->status = 'active';
        $rh->save();

        $targetRh = Employee::query()->create([
            'email' => 'target-rh@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'rh',
        ]);
        $targetRh->company_id = $company->id;
        $targetRh->role = 'manager';
        $targetRh->status = 'active';
        $targetRh->save();


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
            'email' => 'principal@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'principal',
        ]);
        $principal->company_id = $company->id;
        $principal->role = 'manager';
        $principal->status = 'active';
        $principal->save();

        $targetRh = Employee::query()->create([
            'email' => 'target-rh@a.test',
            'password_hash' => Hash::make('password123'),
            'manager_role' => 'rh',
        ]);
        $targetRh->company_id = $company->id;
        $targetRh->role = 'manager';
        $targetRh->status = 'active';
        $targetRh->save();


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
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employeeA->company_id = $companyA->id;
        $employeeA->role = 'employee';
        $employeeA->status = 'active';
        $employeeA->save();


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

