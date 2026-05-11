<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ExpenseClaimControllerTest extends TestCase
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

    public function test_employee_can_submit_expense_claim(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/expense-claims', [
            'title' => 'Deplacement client Oran',
            'amount' => 5000,
            'currency' => 'DZD',
            'category' => 'travel',
            'expense_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_employee_can_list_own_expense_claims(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Repas client',
            'amount' => 2000,
            'currency' => 'DZD',
            'category' => 'meal',
            'expense_date' => now(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/expense-claims');
        $response->assertOk();
    }

    public function test_manager_can_approve_expense_claim(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Taxi',
            'amount' => 1500,
            'currency' => 'DZD',
            'category' => 'transport',
            'expense_date' => now(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/expense-claims/{$claim->id}/approve");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_invalid_category_returns_validation_error(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/expense-claims', [
            'title' => 'Test',
            'amount' => 100,
            'category' => 'invalid',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422);
    }
}
