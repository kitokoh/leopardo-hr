<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollPaymentOrder;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollPaymentOrderService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5239 — Phase C : ordre de virement.
 *
 * Préparation depuis le net par employé d'un run validé (réutilisation du
 * format SEPA existant), exécution par le comptable (référence banque +
 * date) puis rapprochement. Golden DZ : 2 bulletins net 50 000 → total
 * 100 000, 2 virements.
 */
class PayrollPaymentOrderFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_prepare_creates_order_from_validated_run(): void
    {
        Storage::fake('local');
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic', actor: null);

        $this->assertSame(PayrollPaymentOrder::STATUS_PREPARED, $order->status);
        $this->assertSame(100000.0, $order->total_amount);
        $this->assertSame(2, $order->transfer_count);
        $this->assertNotNull($order->file_path);
        $this->assertCount(2, $order->items);
        $this->assertSame(100000.0, $order->items->sum('net_amount'));

        Storage::disk('local')->assertExists($order->file_path);
    }

    public function test_prepare_requires_validated_run(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'calculated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $this->expectException(\RuntimeException::class);
        $service->prepare($run, 'csv_generic');
    }

    public function test_mark_executed_sets_bank_reference_and_date(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic');

        $executed = $service->markExecuted($order, 'VIR-2026-0001', '2026-07-01T10:00:00Z');

        $this->assertSame(PayrollPaymentOrder::STATUS_EXECUTED, $executed->status);
        $this->assertSame('VIR-2026-0001', $executed->bank_reference);
        $this->assertNotNull($executed->executed_at);
    }

    public function test_mark_executed_requires_prepared_status(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic');
        $service->markExecuted($order, 'VIR-1');

        $this->expectException(\RuntimeException::class);
        $service->markExecuted($order, 'VIR-2'); // déjà executed
    }

    public function test_mark_executed_requires_bank_reference(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic');

        $this->expectException(\InvalidArgumentException::class);
        $service->markExecuted($order, '   ');
    }

    public function test_reconcile_marks_order_reconciled(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic');
        $service->markExecuted($order, 'VIR-2026-0001');
        $reconciled = $service->reconcile($order);

        $this->assertSame(PayrollPaymentOrder::STATUS_RECONCILED, $reconciled->status);
        $this->assertNotNull($reconciled->reconciled_at);
    }

    public function test_reconcile_requires_executed_status(): void
    {
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollPaymentOrderService(new BankExportGenerator);
        $order = $service->prepare($run, 'csv_generic');

        $this->expectException(\RuntimeException::class);
        $service->reconcile($order); // encore prepared
    }

    // ── API ────────────────────────────────────────────────────────────────

    public function test_api_prepare_requires_comptable(): void
    {
        Storage::fake('local');
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        // Principal → 403
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/payment-order")->assertForbidden();

        // RH → 403
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        Sanctum::actingAs($rh);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/payment-order")->assertForbidden();

        // Comptable → 201
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);
        Sanctum::actingAs($comptable);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/payment-order", ['format' => 'csv_generic'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'prepared')
            ->assertJsonPath('data.total_amount', 100000);
    }

    public function test_api_execute_flow_with_comptable(): void
    {
        Storage::fake('local');
        [$company, $run, $employees] = $this->runWithSlips('DZ', status: 'validated');

        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);
        Sanctum::actingAs($comptable);

        $orderId = $this->postJson("/api/v1/payroll-runs/{$run->id}/payment-order", ['format' => 'csv_generic'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/payment-orders/{$orderId}/execute", [
            'bank_reference' => 'VIR-2026-0042',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.bank_reference', 'VIR-2026-0042');

        $this->postJson("/api/v1/payment-orders/{$orderId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.status', 'reconciled');

        // Lecture par le principal
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);
        $this->getJson('/api/v1/payment-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'reconciled');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: PayrollRun, 2: array<int, Employee>}
     */
    private function runWithSlips(string $country = 'DZ', string $status = 'validated'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'matricule' => null,
            'iban' => 'DZ0000000000000000000000',
        ]);
        /** @var Employee $employee2 */
        $employee2 = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'matricule' => null,
            'iban' => 'DZ0000000000000000000001',
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => $country,
            'status' => $status,
        ]);

        foreach ([$employee, $employee2] as $target) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $target->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'employer_contributions' => 9000,
                'total_cost' => 69000,
                'status' => $status,
            ]);

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'amount' => 5000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Impot sur le revenu',
                'type' => 'deduction',
                'amount' => 3000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Avance',
                'type' => 'deduction',
                'amount' => 2000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations patronales',
                'type' => 'employer_contribution',
                'amount' => 9000,
            ]);
        }

        return [$company, $run, [$employee, $employee2]];
    }
}
