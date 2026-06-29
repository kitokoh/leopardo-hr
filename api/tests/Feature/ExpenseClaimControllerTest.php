<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
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

    public function test_manager_can_reject_expense_claim_with_reason(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Frais de deplacement',
            'total_amount' => 3000,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", [
            'reason' => 'Justificatifs manquants',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
    }

    public function test_reject_requires_reason_field(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Frais divers',
            'total_amount' => 1000,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", [])
            ->assertStatus(422);
    }

    public function test_non_manager_cannot_approve(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $coworker = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $coworker->id,
            'title'        => 'Taxi',
            'total_amount' => 800,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/approve")
            ->assertStatus(403);
    }

    public function test_non_manager_cannot_reject(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $coworker = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $coworker->id,
            'title'        => 'Repas client',
            'total_amount' => 2500,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", [
            'reason' => 'test',
        ])->assertStatus(403);
    }

    public function test_manager_from_foreign_tenant_gets_404_on_approve(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $foreignManager = Employee::factory()->managerRh()->create(['company_id' => $otherCompany->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Hotel conference',
            'total_amount' => 15000,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($foreignManager);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/approve")
            ->assertNotFound();
    }

    public function test_manager_from_foreign_tenant_gets_404_on_reject(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $foreignManager = Employee::factory()->managerRh()->create(['company_id' => $otherCompany->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Formation externe',
            'total_amount' => 8000,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($foreignManager);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", [
            'reason' => 'Tentative cross-tenant',
        ])->assertNotFound();
    }

    public function test_employee_cannot_submit_already_submitted_claim(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Achat fournitures',
            'total_amount' => 500,
            'currency'     => 'DZD',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")
            ->assertStatus(422);
    }

    public function test_show_returns_404_for_cross_tenant(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $foreignEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'employee',
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Deplacement Alger',
            'total_amount' => 4500,
            'currency'     => 'DZD',
            'status'       => 'draft',
        ]);

        Sanctum::actingAs($foreignEmployee);

        $this->getJson("/api/v1/expense-claims/{$claim->id}")
            ->assertNotFound();
    }
}
