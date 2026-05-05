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

    public function test_employee_cannot_list_other_employees_advances(): void
    {
        $company = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $employee1 = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        $employee2 = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        SalaryAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee1->id,
            'amount' => 1000,
            'status' => 'pending',
            'reason' => 'Advance 1',
        ]);

        SalaryAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 2000,
            'status' => 'pending',
            'reason' => 'Advance 2',
        ]);

        Sanctum::actingAs($employee1);

        $response = $this->getJson('/api/v1/salary-advances');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employee1->id);
    }

    public function test_cannot_access_salary_advance_from_another_tenant(): void
    {
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $employeeB = Employee::factory()->create(['company_id' => $companyB->id, 'role' => 'employee']);

        $advanceB = SalaryAdvance::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 5000,
            'status' => 'pending',
            'reason' => 'Advance B',
        ]);

        Sanctum::actingAs($managerA);

        // Show
        $this->getJson("/api/v1/salary-advances/{$advanceB->id}")->assertStatus(404);

        // Approve
        $this->putJson("/api/v1/salary-advances/{$advanceB->id}/approve", ['repayment_months' => 3])
            ->assertStatus(404);

        // Reject
        $this->putJson("/api/v1/salary-advances/{$advanceB->id}/reject", ['decision_comment' => 'No'])
            ->assertStatus(404);

        // Delete
        $this->deleteJson("/api/v1/salary-advances/{$advanceB->id}")->assertStatus(404);
    }

    public function test_employee_cannot_approve_own_advance(): void
    {
        $company = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        $advance = SalaryAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 5000,
            'status' => 'pending',
            'reason' => 'My advance',
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/salary-advances/{$advance->id}/approve", ['repayment_months' => 3])
            ->assertStatus(403);
    }
}
