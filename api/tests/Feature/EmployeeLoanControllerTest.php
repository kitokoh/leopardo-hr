<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeLoanControllerTest extends TestCase
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

    public function test_employee_can_request_loan(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/loans', [
            'amount' => 50000,
            'currency' => 'DZD',
            'reason' => 'Achat immobilier',
            'repayment_months' => 12,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 50000);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_employee_can_list_own_loans(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 30000,
            'currency' => 'DZD',
            'repayment_months' => 6,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/loans');
        $response->assertOk();
    }

    public function test_manager_can_approve_loan(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $loan = EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 20000,
            'currency' => 'DZD',
            'repayment_months' => 6,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/loans/{$loan->id}/approve");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_employee_cannot_approve_loan(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $loan = EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 10000,
            'currency' => 'DZD',
            'repayment_months' => 3,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/loans/{$loan->id}/approve")->assertStatus(403);
    }
}
