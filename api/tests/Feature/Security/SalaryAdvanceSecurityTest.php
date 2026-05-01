<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Support\Facades\Hash;
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

    public function test_manager_cannot_access_advance_from_another_company(): void
    {
        $companyA = $this->createTestCompany('Company A');
        $companyB = $this->createTestCompany('Company B');

        $managerA = $this->createTestEmployee($companyA, 'manager', 'principal', 'mgrA@test.com');
        $employeeB = $this->createTestEmployee($companyB, 'employee', null, 'empB@test.com');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($managerA);

        // Try to show advance from another company
        $response = $this->getJson("/api/v1/salary-advances/{$advanceB->id}");
        $response->assertStatus(404);

        // Try to approve advance from another company
        $response = $this->putJson("/api/v1/salary-advances/{$advanceB->id}/approve", [
            'repayment_months' => 3,
        ]);
        $response->assertStatus(404);

        // Try to reject advance from another company
        $response = $this->putJson("/api/v1/salary-advances/{$advanceB->id}/reject", [
            'decision_comment' => 'Rejected cross-tenant',
        ]);
        $response->assertStatus(404);
    }

    public function test_employee_can_only_see_their_own_advances(): void
    {
        $company = $this->createTestCompany('Company');
        $employeeA = $this->createTestEmployee($company);
        $employeeB = $this->createTestEmployee($company, 'employee', null, 'empB@test.com');

        $advanceA = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'amount' => 200,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employeeA);

        // Employee A can see their own advance
        $response = $this->getJson("/api/v1/salary-advances/{$advanceA->id}");
        $response->assertStatus(200);

        // Employee A cannot see Employee B's advance
        $response = $this->getJson("/api/v1/salary-advances/{$advanceB->id}");
        $response->assertStatus(403);

        // Employee A's index only contains their own advance
        $response = $this->getJson('/api/v1/salary-advances');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $advanceA->id);
    }

    public function test_employee_cannot_approve_or_reject_advances(): void
    {
        $company = $this->createTestCompany('Company');
        $employee = $this->createTestEmployee($company);

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        // Try to approve
        $response = $this->putJson("/api/v1/salary-advances/{$advance->id}/approve");
        $response->assertStatus(403);

        // Try to reject
        $response = $this->putJson("/api/v1/salary-advances/{$advance->id}/reject");
        $response->assertStatus(403);
    }

    public function test_manager_cannot_see_other_company_advances_in_index(): void
    {
        $companyA = $this->createTestCompany('Company A');
        $companyB = $this->createTestCompany('Company B');

        $managerA = $this->createTestEmployee($companyA, 'manager', 'principal', 'mgrA_index@test.com');
        $employeeA = $this->createTestEmployee($companyA, 'employee', null, 'empA_index@test.com');
        $employeeB = $this->createTestEmployee($companyB, 'employee', null, 'empB_index@test.com');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 200,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($managerA);

        // Manager A should only see advance from Company A
        $response = $this->getJson('/api/v1/salary-advances');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.amount', 100);

        // Try to filter by employee from Company B
        $response = $this->getJson("/api/v1/salary-advances?employee_id={$employeeB->id}");
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    private function createTestCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'sector' => 'IT',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower($name).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createTestEmployee(Company $company, string $role = 'employee', ?string $managerRole = null, string $email = 'test@test.com'): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password'),
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);
    }
}
