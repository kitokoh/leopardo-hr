<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Payroll\Domain\Models\Payment;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Réconciliation paiements ↔ factures — DEP-BC21 (issue #6249).
 *
 * Couvre : dry-run sans effet, `--apply` ne corrige que les écarts sûrs
 * (montant + devise), écart de montant signalé sans correction, facture paid
 * sans paiement signalée, doublons de provider_reference signalés, code
 * retour 0/2, idempotence (`--apply` rejoué ne change plus rien).
 */
class BillingReconciliationTest extends TestCase
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

    private function companyWithSubscription(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function invoice(Company $company, string $status = 'sent', string $total = '100.00'): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => Subscription::where('company_id', $company->id)->firstOrFail()->id,
            'number' => 'INV-'.strtoupper(substr(uniqid('', true), -6)),
            'amount' => $total,
            'currency' => 'USD',
            'tax_amount' => '0.00',
            'total' => $total,
            'status' => $status,
            'due_date' => now()->addDays(15),
        ]);

        return $invoice;
    }

    private function completedPayment(Company $company, Invoice $invoice, string $amount, ?string $ref = null): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'amount' => $amount,
            'currency' => 'USD',
            'method' => 'stripe',
            'provider_reference' => $ref ?? 'pi_'.substr(uniqid('', true), -16),
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
        ]);

        return $payment;
    }

    public function test_dry_run_does_not_mutate_anything(): void
    {
        $company = $this->companyWithSubscription();
        $invoice = $this->invoice($company, 'sent');
        $this->completedPayment($company, $invoice, '100.00');

        $this->artisan('billing:reconcile-payments')
            ->expectsExitCode(2)
            ->expectsOutputToContain('payment_completed_invoice_not_paid');

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_apply_marks_invoice_paid_when_amount_matches(): void
    {
        $company = $this->companyWithSubscription();
        $invoice = $this->invoice($company, 'sent', '100.00');
        $this->completedPayment($company, $invoice, '100.00');

        $this->artisan('billing:reconcile-payments --apply')
            ->expectsExitCode(0);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_apply_is_idempotent(): void
    {
        $company = $this->companyWithSubscription();
        $invoice = $this->invoice($company, 'sent', '100.00');
        $this->completedPayment($company, $invoice, '100.00');

        $this->artisan('billing:reconcile-payments --apply')->expectsExitCode(0);
        $this->artisan('billing:reconcile-payments --apply')->expectsExitCode(0);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_amount_mismatch_is_reported_never_corrected(): void
    {
        $company = $this->companyWithSubscription();
        $invoice = $this->invoice($company, 'sent', '100.00');
        $this->completedPayment($company, $invoice, '90.00');

        $this->artisan('billing:reconcile-payments --apply')
            ->expectsExitCode(2)
            ->expectsOutputToContain('amount_mismatch');

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_invoice_paid_without_payment_is_reported(): void
    {
        $company = $this->companyWithSubscription();
        $this->invoice($company, 'paid', '100.00');

        $this->artisan('billing:reconcile-payments')
            ->expectsExitCode(2)
            ->expectsOutputToContain('invoice_paid_without_payment');
    }

    public function test_duplicate_provider_reference_is_reported(): void
    {
        $company = $this->companyWithSubscription();
        $invoice = $this->invoice($company, 'sent', '100.00');
        $this->completedPayment($company, $invoice, '100.00', 'pi_dup_1234567890');
        $this->completedPayment($company, $invoice, '100.00', 'pi_dup_1234567890');

        $this->artisan('billing:reconcile-payments')
            ->expectsExitCode(2)
            ->expectsOutputToContain('duplicate_provider_reference');
    }

    public function test_reconciliation_operates_cross_tenant_in_console(): void
    {
        // Deux tenants, un écart chacun — la commande console traite les deux
        // sans contexte tenant (aucune fuite en surface API).
        $companyA = $this->companyWithSubscription();
        $invoiceA = $this->invoice($companyA, 'sent', '50.00');
        $this->completedPayment($companyA, $invoiceA, '50.00');

        $companyB = $this->companyWithSubscription();
        $invoiceB = $this->invoice($companyB, 'sent', '75.00');
        $this->completedPayment($companyB, $invoiceB, '75.00');

        $this->artisan('billing:reconcile-payments --apply')
            ->expectsExitCode(0);

        $this->assertSame('paid', $invoiceA->fresh()->status);
        $this->assertSame('paid', $invoiceB->fresh()->status);
    }
}
