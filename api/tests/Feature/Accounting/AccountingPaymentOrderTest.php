<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingPaymentOrder;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Ordres de virement salarial — flux Paie → Comptabilité (issue #5239, Phase C).
 *
 * Workflow : création depuis un run validé (net total) → préparation (export
 * banque réutilisant les formats Payroll) → exécution par le comptable
 * (référence banque + date = rapprochement). RBAC comptable/principal,
 * isolation tenant, idempotence de création.
 */
class AccountingPaymentOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function manager(Company $company, string $managerRole): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    /**
     * Run de paie DZ avec 2 bulletins validés (net 50 000 + 50 000 = 100 000)
     * — mêmes montants que PayrollAccountingExportJournalTest (#5256).
     *
     * @return array{0: PayrollRun, 1: Company}
     */
    private function validatedRun(Company $company): array
    {
        /** @var Employee $e1 */
        $e1 = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'matricule' => null]);
        /** @var Employee $e2 */
        $e2 = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Marie', 'last_name' => 'Martin', 'matricule' => null]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'locked',
        ]);

        foreach ([$e1, $e2] as $employee) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $employee->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'employer_contributions' => 9000,
                'total_cost' => 69000,
                'status' => 'validated',
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'amount' => 5000,
            ]);
        }

        return [$run, $company];
    }

    // ── US1 : création depuis un run validé ──────────────────────────────────

    public function test_store_creates_draft_order_with_total_net(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $response = $this->postJson('/api/v1/accounting/payment-orders', [
            'payroll_run_id' => $run->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payroll_run_id', $run->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_net', 100000.0)
            ->assertJsonPath('data.currency', 'DZD');
    }

    public function test_store_is_idempotent_per_run(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->assertStatus(201);
        $second = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id]);

        $second->assertStatus(201);
        $this->assertSame(1, AccountingPaymentOrder::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_store_rejects_non_validated_run(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'calculated',
        ]);
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PAYROLL_RUN_NOT_VALIDATED');
    }

    public function test_store_cross_tenant_run_returns_404(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run, $otherCompany] = $this->validatedRun($this->company());
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])
            ->assertStatus(404);
    }

    // ── US2 : préparation → exécution ────────────────────────────────────────

    public function test_prepare_generates_bank_export_and_marks_prepared(): void
    {
        Storage::fake('local');
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');

        $response = $this->postJson("/api/v1/accounting/payment-orders/{$order}/prepare", ['format' => 'csv_generic']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'prepared')
            ->assertJsonPath('data.export_format', 'csv_generic');

        $this->assertNotNull($response->json('data.export_file'));
        Storage::disk('local')->assertExists($response->json('data.export_file'));
    }

    public function test_prepare_rejects_invalid_format(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');

        $this->postJson("/api/v1/accounting/payment-orders/{$order}/prepare", ['format' => 'pdf'])
            ->assertStatus(422);
    }

    public function test_execute_marks_executed_with_bank_reference(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');
        $this->postJson("/api/v1/accounting/payment-orders/{$order}/prepare", ['format' => 'csv_generic'])->assertOk();

        $response = $this->postJson("/api/v1/accounting/payment-orders/{$order}/execute", [
            'bank_reference' => 'VIREMENT-2026-07-001',
            'executed_at' => '2026-07-05',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.bank_reference', 'VIREMENT-2026-07-001');
        $this->assertNotNull($response->json('data.executed_at'));
    }

    public function test_execute_without_bank_reference_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');
        $this->postJson("/api/v1/accounting/payment-orders/{$order}/prepare", ['format' => 'csv_generic'])->assertOk();

        $this->postJson("/api/v1/accounting/payment-orders/{$order}/execute", [])
            ->assertStatus(422);
    }

    public function test_execute_on_draft_order_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');

        $this->postJson("/api/v1/accounting/payment-orders/{$order}/execute", ['bank_reference' => 'X'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PAYMENT_ORDER_NOT_EXECUTABLE');
    }

    public function test_double_execution_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');
        $this->postJson("/api/v1/accounting/payment-orders/{$order}/prepare", ['format' => 'csv_generic'])->assertOk();
        $this->postJson("/api/v1/accounting/payment-orders/{$order}/execute", ['bank_reference' => 'R1'])->assertOk();

        $this->postJson("/api/v1/accounting/payment-orders/{$order}/execute", ['bank_reference' => 'R2'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PAYMENT_ORDER_NOT_EXECUTABLE');
    }

    // ── US3 : RBAC + isolation tenant ────────────────────────────────────────

    public function test_rbac_principal_can_read_but_not_write(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);

        Sanctum::actingAs($this->manager($company, 'principal'));
        $this->getJson('/api/v1/accounting/payment-orders')->assertOk();
        $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])
            ->assertStatus(403);
    }

    public function test_rbac_rh_and_employee_are_forbidden(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        Sanctum::actingAs($this->manager($company, 'rh'));
        $this->getJson('/api/v1/accounting/payment-orders')->assertStatus(403);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee', 'manager_role' => null]);
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/accounting/payment-orders')->assertStatus(403);
    }

    public function test_cross_tenant_order_is_invisible(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->validatedRun($company);
        Sanctum::actingAs($this->manager($company, 'comptable'));
        $order = $this->postJson('/api/v1/accounting/payment-orders', ['payroll_run_id' => $run->id])->json('data.id');

        // Autre entreprise : l'ordre doit être invisible (404 fail-closed).
        $other = $this->company();
        $this->bindCompany($other);
        Sanctum::actingAs($this->manager($other, 'comptable'));

        $this->getJson("/api/v1/accounting/payment-orders/{$order}")->assertStatus(404);
        $this->getJson('/api/v1/accounting/payment-orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
