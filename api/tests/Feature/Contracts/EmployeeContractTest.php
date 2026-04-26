<?php

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeContractTest extends TestCase
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

    public function test_employees_index_payload_includes_matricule_and_company_id(): void
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
            'matricule' => 'MGR-001',
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-001',
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'matricule',
                    'company_id',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'status',
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.matricule', 'MGR-001');
        $response->assertJsonPath('data.0.company_id', $company->id);
    }

    public function test_employee_show_payload_includes_matricule_and_company_id(): void
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
            'matricule' => 'MGR-001',
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/employees/{$manager->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'matricule',
                'company_id',
                'first_name',
                'last_name',
                'email',
                'role',
                'manager_role',
                'status',
            ],
        ]);
        $response->assertJsonPath('data.matricule', 'MGR-001');
        $response->assertJsonPath('data.company_id', $company->id);
    }

    public function test_employee_store_payload_includes_matricule_and_company_id(): void
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
            'matricule' => 'MGR-001',
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'New',
            'last_name' => 'Employee',
            'email' => 'new@company.test',
            'role' => 'employee',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'matricule',
                'company_id',
                'first_name',
                'last_name',
                'email',
                'status',
            ],
        ]);
        $response->assertJsonPath('data.company_id', $company->id);
    }

    public function test_employee_update_payload_includes_matricule_and_company_id(): void
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
            'matricule' => 'MGR-001',
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->patchJson("/api/v1/employees/{$manager->id}", [
            'first_name' => 'Updated',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'matricule',
                'company_id',
                'first_name',
                'last_name',
                'email',
                'status',
            ],
        ]);
        $response->assertJsonPath('data.matricule', 'MGR-001');
        $response->assertJsonPath('data.company_id', $company->id);
    }
}
