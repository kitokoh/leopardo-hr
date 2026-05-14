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
            'items' => [[
                'category' => 'transport',
                'description' => 'Taxi client',
                'amount' => 5000,
                'date' => now()->subDay()->toDateString(),
            ]],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
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
            'total_amount' => 2000,
            'currency' => 'DZD',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/expense-claims');
        $response->assertOk();
    }

    public function test_manager_can_approve_expense_claim(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Taxi',
            'total_amount' => 1500,
            'currency' => 'DZD',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/expense-claims/{$claim->id}/approve");
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
            'items' => [[
                'category' => 'invalid',
                'description' => 'Test',
                'amount' => 100,
                'date' => now()->toDateString(),
            ]],
        ])->assertStatus(422);
    }

    public function test_expense_claims_are_scoped_by_tenant_and_owner(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $coworker = Employee::factory()->create(['company_id' => $company->id]);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Own claim',
            'total_amount' => 2000,
            'currency' => 'DZD',
            'status' => 'submitted',
        ]);
        ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $coworker->id,
            'title' => 'Coworker claim',
            'total_amount' => 3000,
            'currency' => 'DZD',
            'status' => 'submitted',
        ]);
        $foreignClaim = ExpenseClaim::create([
            'company_id' => $otherCompany->id,
            'employee_id' => $foreignEmployee->id,
            'title' => 'Foreign claim',
            'total_amount' => 4000,
            'currency' => 'DZD',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/expense-claims')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/expense-claims/{$foreignClaim->id}")->assertNotFound();

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/expense-claims')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->putJson("/api/v1/expense-claims/{$foreignClaim->id}/approve")->assertNotFound();
    }
}
