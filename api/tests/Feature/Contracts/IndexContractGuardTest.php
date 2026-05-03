<?php

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class IndexContractGuardTest extends TestCase
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

    public function test_index_endpoints_reject_cross_tenant_employee_id(): void
    {
        // Company A & Manager A
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Manager',
            'last_name' => 'A',
            'email' => 'manager.a@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        // Company B & Employee B
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $companyB->id,
            'first_name' => 'Employee',
            'last_name' => 'B',
            'email' => 'employee.b@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $endpoints = [
            '/api/v1/salary-advances',
            '/api/v1/absences',
            '/api/v1/payrolls',
            '/api/v1/attendance',
            '/api/v1/attendance/today',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint . '?employee_id=' . $employeeB->id);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['employee_id']);
            $this->assertEquals(
                'Employé introuvable dans votre entreprise.',
                $response->json('errors.employee_id.0')
            );
        }
    }

    public function test_index_endpoints_allow_own_tenant_employee_id(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Manager',
            'last_name' => 'A',
            'email' => 'manager.a@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Employee',
            'last_name' => 'A',
            'email' => 'employee.a@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $endpoints = [
            '/api/v1/salary-advances',
            '/api/v1/absences',
            '/api/v1/payrolls',
            '/api/v1/attendance',
            '/api/v1/attendance/today',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint . '?employee_id=' . $employeeA->id);
            $response->assertOk();
        }
    }
}
