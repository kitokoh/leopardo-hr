<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-14 (#1602) : workflow complet de l'avance sur salaire
 * (création → approbation manager → paiement déclaré → confirmation employé),
 * qui exerçait `SalaryAdvanceController` sans couvrir le flux métier entier.
 */
class SalaryAdvanceWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    public function test_full_advance_workflow_employee_to_confirmed(): void
    {
        // 1. L'employé crée la demande.
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', [
            'amount' => 10000,
            'reason' => 'Avance sur salaire juillet',
            'repayment_months' => 4,
        ])->assertCreated()->json('data');

        $advanceId = $created['id'];
        $this->assertSame('pending', $created['status']);
        $this->assertSame('pending', $created['validation_status']);

        // 2. Le manager approuve (double validation).
        Sanctum::actingAs($this->manager);
        $approved = $this->putJson("/api/v1/salary-advances/{$advanceId}/manager-approve")
            ->assertOk()
            ->json('data');
        $this->assertSame('approved', $approved['status']);
        $this->assertSame('manager_approved', $approved['validation_status']);

        // 3. Le manager déclare le paiement → document + écriture comptable.
        $paid = $this->putJson("/api/v1/salary-advances/{$advanceId}/mark-paid", [
            'payment_reference' => 'VIR-2026-0715-001',
            'payment_note' => 'Virement effectué',
        ])->assertOk()->json('data');
        $this->assertSame('payment_declared', $paid['validation_status']);
        $this->assertSame('VIR-2026-0715-001', $paid['payment_reference']);

        $this->assertDatabaseHas('payment_documents', [
            'company_id' => $this->company->id,
            'salary_advance_id' => $advanceId,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
        ]);

        // 4. L'employé confirme la réception.
        Sanctum::actingAs($this->employee);
        $confirmed = $this->putJson("/api/v1/salary-advances/{$advanceId}/confirm-received")
            ->assertOk()
            ->json('data');
        $this->assertSame('employee_confirmed', $confirmed['validation_status']);
        $this->assertNotNull($confirmed['employee_confirmed_at']);
    }

    public function test_mark_paid_requires_manager_approval_first(): void
    {
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', ['amount' => 5000])->assertCreated()->json('data');

        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/salary-advances/{$created['id']}/mark-paid")
            ->assertStatus(422)
            ->assertJsonPath('message', 'L\'avance doit être approuvée par le manager avant de déclarer le paiement.');
    }

    public function test_mark_paid_second_call_is_rejected_no_duplicate_document(): void
    {
        // Issue #3429 (classe #2997) : TOCTOU — un double appel mark-paid
        // (même consécutif, simulé par un 2e appel après le 1er succès) ne
        // doit produire qu'UN document + UNE écriture ledger.
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', ['amount' => 8000])->assertCreated()->json('data');
        $advanceId = $created['id'];

        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/salary-advances/{$advanceId}/manager-approve")->assertOk();

        $this->putJson("/api/v1/salary-advances/{$advanceId}/mark-paid")->assertOk();

        // 2e appel : l'update conditionnel ne matche plus → 422, pas de doublon.
        $this->putJson("/api/v1/salary-advances/{$advanceId}/mark-paid")
            ->assertStatus(422)
            ->assertJsonPath('message', 'L\'avance doit être approuvée par le manager avant de déclarer le paiement.');

        $this->assertDatabaseCount('payment_documents', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_confirm_received_requires_payment_declared(): void
    {
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', ['amount' => 5000])->assertCreated()->json('data');

        $this->putJson("/api/v1/salary-advances/{$created['id']}/confirm-received")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Le paiement doit être déclaré avant la confirmation de l\'employé.');
    }

    public function test_only_owner_can_confirm_reception(): void
    {
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', ['amount' => 5000])->assertCreated()->json('data');

        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/salary-advances/{$created['id']}/manager-approve")->assertOk();
        $this->putJson("/api/v1/salary-advances/{$created['id']}/mark-paid")->assertOk();

        /** @var Employee $other */
        $other = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($other);
        $this->putJson("/api/v1/salary-advances/{$created['id']}/confirm-received")->assertStatus(403);
    }

    public function test_employee_cannot_approve_advance(): void
    {
        Sanctum::actingAs($this->employee);
        $created = $this->postJson('/api/v1/salary-advances', ['amount' => 5000])->assertCreated()->json('data');

        $this->putJson("/api/v1/salary-advances/{$created['id']}/manager-approve")->assertStatus(403);
        $this->putJson("/api/v1/salary-advances/{$created['id']}/mark-paid")->assertStatus(403);
    }
}
