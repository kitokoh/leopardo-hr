<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
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
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $managerA->createToken('tests')->plainTextToken;

        $ok = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/employees/{$employeeA->id}/archive");
        $ok->assertOk();
        $ok->assertJsonPath('data.status', 'archived');

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
