<?php

namespace Tests\Feature\Expense;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #1491 — le module Expense (coquille : controller branché sur les
 * modèles du module Planning, dérogation PA2-ARCH-011) n'avait AUCUN test
 * dédié. Ces tests couvrent le workflow réel des routes /api/v1/expense-claims
 * (création → soumission → approbation/rejet, isolation tenant).
 */
class ExpenseClaimWorkflowTest extends TestCase
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

    private function validPayload(): array
    {
        return [
            'title'       => 'Déplacement client Alger',
            'description' => 'Mission commerciale',
            'items'       => [
                [
                    'category'    => 'transport',
                    'description' => 'Billet de train',
                    'amount'      => 2500.50,
                    'date'        => '2026-07-20',
                ],
                [
                    'category'    => 'meals',
                    'description' => 'Repas',
                    'amount'      => 1200,
                    'date'        => '2026-07-20',
                ],
            ],
        ];
    }

    public function test_employee_can_create_expense_claim_as_draft(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/expense-claims', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.total_amount', 3700.5);

        $this->assertDatabaseHas('expense_claims', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status'     => 'draft',
        ]);
        $this->assertDatabaseCount('expense_items', 2);
    }

    public function test_employee_can_submit_own_claim(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Note de frais',
            'status'       => 'draft',
            'total_amount' => 100,
            'currency'     => 'DZD',
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('expense_claims', ['id' => $claim->id, 'status' => 'submitted']);
    }

    public function test_manager_can_approve_submitted_claim(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Note de frais',
            'status'       => 'submitted',
            'total_amount' => 100,
            'currency'     => 'DZD',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('expense_claims', [
            'id'          => $claim->id,
            'status'      => 'approved',
            'approved_by' => (string) $manager->id,
        ]);
    }

    public function test_manager_cannot_approve_without_reason_after_reject(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Note de frais',
            'status'       => 'submitted',
            'total_amount' => 100,
            'currency'     => 'DZD',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        // Rejet sans raison -> 422
        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject")
            ->assertUnprocessable();

        // Rejet avec raison -> ok
        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", ['reason' => 'Justificatif manquant'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_cross_tenant_claim_is_hidden(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        $claim = ExpenseClaim::create([
            'company_id'   => $companyA->id,
            'employee_id'  => $employeeA->id,
            'title'        => 'Frais société A',
            'status'       => 'submitted',
            'total_amount' => 100,
            'currency'     => 'DZD',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($managerB);

        // Pas de fuite d'existence cross-tenant : 404, pas 403
        $this->getJson("/api/v1/expense-claims/{$claim->id}")->assertNotFound();
        $this->putJson("/api/v1/expense-claims/{$claim->id}/approve")->assertNotFound();
        $this->putJson("/api/v1/expense-claims/{$claim->id}/reject", ['reason' => 'x'])->assertNotFound();
    }

    public function test_employee_cannot_approve_own_claim(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $claim = ExpenseClaim::create([
            'company_id'   => $company->id,
            'employee_id'  => $employee->id,
            'title'        => 'Note de frais',
            'status'       => 'submitted',
            'total_amount' => 100,
            'currency'     => 'DZD',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/approve")->assertForbidden();
    }
}
