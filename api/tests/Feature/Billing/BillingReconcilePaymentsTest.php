<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6249) — réconciliation paiements ↔ factures.
 *
 * `billing:reconcile-payments` : dry-run par défaut (aucune mutation),
 * `--apply` corrige uniquement les écarts sûrs (montant + company
 * concordants) via la machine à états ; doublons et factures paid sans
 * paiement sont signalés, jamais corrigés. Idempotent.
 */
class BillingReconcilePaymentsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function fixture(): array
    {
        $company = Company::factory()->create();
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-'.uniqid(),
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->addDays(10),
        ]);

        return [$company, $subscription, $invoice];
    }

    private function createPayment(int $invoiceId, string $companyId, float $amount, ?string $reference = null): void
    {
        DB::table('payments')->insert([
            'invoice_id' => $invoiceId,
            'company_id' => $companyId,
            'amount' => $amount,
            'currency' => 'EUR',
            'method' => 'card',
            'provider_reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_dry_run_reports_safe_gap_without_mutating(): void
    {
        [$company, , $invoice] = $this->fixture();
        $this->createPayment((int) $invoice->id, (string) $company->id, 99.00, 'ch_ok');

        $exit = Artisan::call('billing:reconcile-payments');
        $output = Artisan::output();

        self::assertSame(2, $exit, 'écart signalé → code retour 2');
        self::assertStringContainsString('corrigeable avec --apply', $output);
        self::assertSame(
            InvoiceStatus::Sent->value,
            $invoice->refresh()->status,
            'dry-run : aucune mutation'
        );
    }

    public function test_apply_marks_invoice_paid_and_is_idempotent(): void
    {
        [$company, , $invoice] = $this->fixture();
        $this->createPayment((int) $invoice->id, (string) $company->id, 99.00, 'ch_apply');

        Artisan::call('billing:reconcile-payments', ['--apply' => true]);

        self::assertSame(
            InvoiceStatus::Paid->value,
            $invoice->refresh()->status,
            'écart sûr corrigé via la machine à états'
        );
        self::assertNotNull($invoice->paid_at);

        $exit = Artisan::call('billing:reconcile-payments', ['--apply' => true]);

        self::assertSame(0, $exit, 'rejoué sur un état cohérent → aucun écart (idempotent)');
    }

    public function test_amount_mismatch_is_reported_but_never_applied(): void
    {
        [$company, , $invoice] = $this->fixture();
        $this->createPayment((int) $invoice->id, (string) $company->id, 50.00, 'ch_mismatch');

        $exit = Artisan::call('billing:reconcile-payments', ['--apply' => true]);
        $output = Artisan::output();

        self::assertSame(2, $exit);
        self::assertStringContainsString('montant incohérent', $output);
        self::assertSame(
            InvoiceStatus::Sent->value,
            $invoice->refresh()->status,
            'un écart de montant n\'est jamais corrigé automatiquement'
        );
    }

    public function test_duplicate_provider_reference_is_reported(): void
    {
        [$company, , $invoice] = $this->fixture();
        $this->createPayment((int) $invoice->id, (string) $company->id, 99.00, 'ch_dupe');
        $this->createPayment((int) $invoice->id, (string) $company->id, 99.00, 'ch_dupe');

        $exit = Artisan::call('billing:reconcile-payments');
        $output = Artisan::output();

        self::assertSame(2, $exit);
        self::assertStringContainsString('doublon provider_reference', $output);
    }

    public function test_paid_invoice_without_completed_payment_is_reported(): void
    {
        [$company, , $invoice] = $this->fixture();
        $invoice->transitionTo(InvoiceStatus::Paid, ['paid_at' => now()]);

        $exit = Artisan::call('billing:reconcile-payments');
        $output = Artisan::output();

        self::assertSame(2, $exit);
        self::assertStringContainsString('sans paiement completed', $output);
    }

    public function test_clean_state_returns_success(): void
    {
        [$company, , $invoice] = $this->fixture();
        $this->createPayment((int) $invoice->id, (string) $company->id, 99.00, 'ch_clean');
        $invoice->transitionTo(InvoiceStatus::Paid, ['paid_at' => now()]);

        $exit = Artisan::call('billing:reconcile-payments');

        self::assertSame(0, $exit, 'état cohérent → aucun écart');
    }
}
