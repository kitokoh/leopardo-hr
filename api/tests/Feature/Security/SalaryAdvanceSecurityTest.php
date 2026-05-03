<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SalaryAdvanceSecurityTest extends TestCase
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

    public function test_manager_cannot_filter_salary_advances_by_another_tenant_employee_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        // This should return 422 because employeeB doesn't belong to companyA
        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/salary-advances?employee_id={$employeeB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_employee_cannot_see_others_salary_advances(): void
    {
        $companyA = $this->createCompany('Company A');
        $employeeA1 = $this->createEmployee($companyA, 'employee');
        $employeeA2 = $this->createEmployee($companyA, 'employee');

        SalaryAdvance::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA2->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employeeA1, 'sanctum')
            ->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_access_to_salary_advance_detail_returns_404(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/salary-advances/{$advanceB->id}");

        $response->assertStatus(404);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
