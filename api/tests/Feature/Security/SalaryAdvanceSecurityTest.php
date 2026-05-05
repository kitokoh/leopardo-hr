<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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

    public function test_employee_sees_only_own_salary_advances_in_index(): void
    {
        $company = $this->createCompany('Company A');
        $employeeA = $this->createEmployee($company, 'employee');
        $employeeB = $this->createEmployee($company, 'employee');

        SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'amount' => 5000,
        ]);

        SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'amount' => 10000,
        ]);

        Sanctum::actingAs($employeeA);

        $response = $this->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employeeA->id);
        $response->assertJsonPath('data.0.amount', 5000);
    }

    public function test_employee_cannot_view_others_salary_advance_detail(): void
    {
        $company = $this->createCompany('Company A');
        $employeeA = $this->createEmployee($company, 'employee');
        $employeeB = $this->createEmployee($company, 'employee');

        $advanceB = SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
        ]);

        Sanctum::actingAs($employeeA);

        $response = $this->getJson("/api/v1/salary-advances/{$advanceB->id}");

        $response->assertStatus(403);
    }

    public function test_manager_cannot_view_salary_advance_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson("/api/v1/salary-advances/{$advanceB->id}");

        // The controller uses abort(404) if company_id doesn't match
        $response->assertStatus(404);
    }

    public function test_manager_cannot_approve_salary_advance_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->putJson("/api/v1/salary-advances/{$advanceB->id}/approve", [
            'decision_comment' => 'Illegal approval',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('pending', $advanceB->fresh()->status);
    }

    public function test_manager_cannot_reject_salary_advance_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->putJson("/api/v1/salary-advances/{$advanceB->id}/reject", [
            'decision_comment' => 'Illegal rejection',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('pending', $advanceB->fresh()->status);
    }

    public function test_employee_cannot_approve_own_salary_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->putJson("/api/v1/salary-advances/{$advance->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals('pending', $advance->fresh()->status);
    }

    public function test_employee_cannot_reject_own_salary_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->putJson("/api/v1/salary-advances/{$advance->id}/reject");

        $response->assertStatus(403);
        $this->assertEquals('pending', $advance->fresh()->status);
    }

    public function test_employee_can_cancel_own_pending_salary_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        // The controller uses DELETE for cancel (destroy method)
        $response = $this->deleteJson("/api/v1/salary-advances/{$advance->id}");

        $response->assertStatus(200);
        $this->assertEquals('rejected', $advance->fresh()->status);
    }

    public function test_employee_cannot_cancel_others_salary_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employeeA = $this->createEmployee($company, 'employee');
        $employeeB = $this->createEmployee($company, 'employee');

        $advanceB = SalaryAdvance::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employeeA);

        $response = $this->deleteJson("/api/v1/salary-advances/{$advanceB->id}");

        $response->assertStatus(403);
        $this->assertEquals('pending', $advanceB->fresh()->status);
    }

    public function test_manager_filtering_by_another_tenant_employee_id_returns_empty_list(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        SalaryAdvance::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson("/api/v1/salary-advances?employee_id={$employeeB->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
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
