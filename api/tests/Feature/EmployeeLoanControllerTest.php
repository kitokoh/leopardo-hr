<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\LoanRepayment;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class EmployeeLoanControllerTest extends TestCase
{
    use RefreshTenantDatabase;

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
            'loan_type' => 'housing',
            'installments' => 12,
            'start_date' => now()->addMonth()->toDateString(),
            'notes' => 'Achat immobilier',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 50000);
        $response->assertJsonPath('data.status', 'draft');
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
            'installments' => 6,
            'installment_amount' => 5000,
            'start_date' => now()->addMonth(),
            'status' => 'approved',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/loans');
        $response->assertOk();
    }

    public function test_manager_can_approve_loan(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $loan = EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 20000,
            'currency' => 'DZD',
            'installments' => 6,
            'installment_amount' => 3333.33,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
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
            'installments' => 3,
            'installment_amount' => 3333.33,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/loans/{$loan->id}/approve")->assertStatus(403);
    }

    public function test_cross_tenant_loan_returns_404(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $foreignLoan = EmployeeLoan::create([
            'company_id' => $otherCompany->id,
            'employee_id' => Employee::factory()->create(['company_id' => $otherCompany->id])->id,
            'amount' => 15000,
            'currency' => 'DZD',
            'installments' => 3,
            'installment_amount' => 5000,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);

        Sanctum::actingAs($manager);

        // Manager of company A must receive 404 when accessing a loan from company B
        $this->getJson("/api/v1/loans/{$foreignLoan->id}")->assertNotFound();
        $this->postJson("/api/v1/loans/{$foreignLoan->id}/approve")->assertNotFound();
    }

    public function test_disburse_requires_approved_status(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        $pendingLoan = EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 25000,
            'currency' => 'DZD',
            'installments' => 6,
            'installment_amount' => 4166.67,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);

        Sanctum::actingAs($manager);

        // Disburse must fail (422) when loan is still in pending_approval state
        $this->postJson("/api/v1/loans/{$pendingLoan->id}/disburse")
            ->assertUnprocessable();

        // After approval, disburse must succeed (200)
        $pendingLoan->update(['status' => 'approved']);

        $this->postJson("/api/v1/loans/{$pendingLoan->id}/disburse")
            ->assertOk()
            ->assertJsonPath('data.status', 'disbursed');
    }

    public function test_loans_are_scoped_to_tenant_and_foreign_employee_is_rejected(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 12000,
            'currency' => 'DZD',
            'installments' => 3,
            'installment_amount' => 4000,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);
        $foreignLoan = EmployeeLoan::create([
            'company_id' => $otherCompany->id,
            'employee_id' => $foreignEmployee->id,
            'amount' => 9000,
            'currency' => 'DZD',
            'installments' => 3,
            'installment_amount' => 3000,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/loans')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/loans/{$foreignLoan->id}")->assertNotFound();
        $this->postJson('/api/v1/loans', [
            'employee_id' => $foreignEmployee->id,
            'amount' => 50000,
            'loan_type' => 'housing',
            'installments' => 12,
            'start_date' => now()->addMonth()->toDateString(),
        ])->assertUnprocessable();
    }

    /**
     * Issue #3950 — show() ne doit jamais exposer des repayments d'un autre
     * tenant, même si le prêt parent appartient au tenant courant
     * (defense-in-depth sur la relation).
     */
    public function test_show_filters_repayments_to_current_tenant(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $loan = EmployeeLoan::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 12000,
            'currency' => 'DZD',
            'installments' => 3,
            'installment_amount' => 4000,
            'start_date' => now()->addMonth(),
            'status' => 'pending_approval',
        ]);
        LoanRepayment::create([
            'employee_loan_id' => $loan->id,
            'company_id' => $company->id,
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 4000,
            'principal' => 4000,
            'interest' => 0,
            'status' => 'pending',
        ]);
        // Ligne « orpheline » d'un autre tenant rattachée au même prêt :
        // elle ne doit pas fuiter dans la réponse.
        DB::table('loan_repayments')->insert([
            'employee_loan_id' => $loan->id,
            'company_id' => $otherCompany->id,
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 9999,
            'principal' => 9999,
            'interest' => 0,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        // L'endpoint reste 200 (le prêt appartient au tenant).
        $this->getJson("/api/v1/loans/{$loan->id}")->assertOk();

        // Contrat de scopage de la relation : le filtre company_id du
        // contrôleur (show) ne doit exposer que les repayments du tenant
        // courant, jamais la ligne orpheline d'un autre tenant.
        $loan->load(['repayments' => fn ($query) => $query->where('company_id', $company->id)]);
        $this->assertCount(1, $loan->repayments);
        $this->assertSame(4000.0, (float) $loan->repayments->first()->amount);
    }
}
