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

    public function test_employee_can_only_see_their_own_salary_advances(): void
    {
        $company = $this->createCompany('Company A');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee1->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employee1->id);
    }

    public function test_manager_can_only_see_salary_advances_within_their_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeA = $this->createEmployee($companyA, 'employee');
        $employeeB = $this->createEmployee($companyB, 'employee');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        // Manager A should only see advance for Employee A
        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employeeA->id);
    }

    public function test_manager_cannot_view_salary_advance_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/salary-advances/{$advanceB->id}");

        $response->assertStatus(404);
    }

    public function test_manager_cannot_approve_salary_advance_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advanceB->id}/approve", [
                'repayment_months' => 3,
            ]);

        $response->assertStatus(404);
    }

    public function test_employee_cannot_cancel_salary_advance_of_another_employee(): void
    {
        $company = $this->createCompany('Company A');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $advance2 = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->deleteJson("/api/v1/salary-advances/{$advance2->id}");

        $response->assertStatus(403);
    }

    public function test_manager_cannot_filter_by_another_tenant_employee_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/salary-advances?employee_id={$employeeB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_non_manager_cannot_approve_salary_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/approve", [
                'repayment_months' => 3,
            ]);

        $response->assertStatus(403);
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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        return $employee;
    }
}
