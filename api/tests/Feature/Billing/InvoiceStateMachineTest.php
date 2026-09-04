<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6248) — machine à états des factures + grace period pilotée par
 * `invoices.due_date`.
 *
 * Toutes les écritures de statut de facture passent par
 * `Invoice::transitionTo()` ; `billing:check-overdue` marque overdue les
 * factures dues ; `billing:enforce-delinquency` traite un tenant en défaut
 * sur la base de sa dernière facture impayée (due_date + grâce) avec repli
 * sur la période de l'abonnement.
 */
class InvoiceStateMachineTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function subscription(Company $company, array $overrides = []): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::create(array_merge([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => SubscriptionStatus::Active->value,
            'payment_method' => 'stripe',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ], $overrides));

        return $subscription;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function invoice(Company $company, Subscription $subscription, array $overrides = []): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::create(array_merge([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-'.uniqid(),
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->addDays(10),
        ], $overrides));

        return $invoice;
    }

    // ── Machine à états ─────────────────────────────────────────────────────

    public function test_invoice_transition_matrix_is_enforced(): void
    {
        self::assertTrue(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Sent));
        self::assertTrue(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Paid));
        self::assertTrue(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Overdue));
        self::assertTrue(InvoiceStatus::Overdue->canTransitionTo(InvoiceStatus::Paid));
        self::assertFalse(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Paid));
        self::assertFalse(InvoiceStatus::Paid->canTransitionTo(InvoiceStatus::Overdue));
        self::assertFalse(InvoiceStatus::Cancelled->canTransitionTo(InvoiceStatus::Paid));
    }

    public function test_invalid_invoice_transition_throws(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $invoice = $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Draft->value,
        ]);

        try {
            $invoice->transitionTo(InvoiceStatus::Paid);
            self::fail('draft → paid doit être refusé (passage par sent requis)');
        } catch (InvalidArgumentException) {
            self::assertSame(InvoiceStatus::Draft->value, $invoice->status);
        }
    }

    public function test_paid_invoice_is_terminal(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $invoice = $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => now(),
        ]);

        try {
            $invoice->transitionTo(InvoiceStatus::Overdue);
            self::fail('une facture payée ne peut pas redevenir overdue');
        } catch (InvalidArgumentException) {
            self::assertSame(InvoiceStatus::Paid->value, $invoice->status);
        }
    }

    public function test_overdue_invoice_can_be_paid(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $invoice = $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Overdue->value,
        ]);

        $invoice->transitionTo(InvoiceStatus::Paid, [
            'paid_at' => now(),
            'payment_method' => 'stripe',
        ]);

        self::assertSame(InvoiceStatus::Paid->value, $invoice->status);
        self::assertNotNull($invoice->paid_at);
    }

    public function test_transition_to_current_status_is_idempotent(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $invoice = $this->invoice($company, $subscription);

        // Rejoué : aucune exception, les attributs additionnels sont synchronisés.
        $invoice->transitionTo(InvoiceStatus::Sent, ['payment_method' => 'stripe']);
        $invoice->transitionTo(InvoiceStatus::Sent);

        self::assertSame(InvoiceStatus::Sent->value, $invoice->status);
    }

    // ── billing:check-overdue ───────────────────────────────────────────────

    public function test_check_overdue_marks_sent_invoices(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);

        $lateSent = $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->subDay(),
        ]);
        $future = $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->addDays(5),
        ]);

        Artisan::call('billing:check-overdue');
        Artisan::call('billing:check-overdue'); // idempotent

        self::assertSame(InvoiceStatus::Overdue->value, $lateSent->refresh()->status);
        self::assertSame(InvoiceStatus::Sent->value, $future->refresh()->status, 'facture non échue → sent conservé');
    }

    // ── billing:enforce-delinquency — grâce pilotée par due_date ────────────

    public function test_active_subscription_with_late_invoice_becomes_past_due(): void
    {
        $company = $this->company();
        // Période NON expirée : seule la facture en retard déclenche le défaut.
        $subscription = $this->subscription($company);
        $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->subDay(),
        ]);

        Artisan::call('billing:enforce-delinquency');

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'facture due et impayée → past_due (politique explicite, même période non expirée)'
        );
    }

    public function test_active_subscription_with_future_invoice_stays_active(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->addDays(3),
        ]);

        Artisan::call('billing:enforce-delinquency');

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'facture non échue → pas de défaut'
        );
    }

    public function test_past_due_beyond_grace_computed_from_invoice_due_date(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::PastDue->value,
        ]);
        // due_date il y a 10 jours, grâce 7 j → expired.
        $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(10),
        ]);

        Artisan::call('billing:enforce-delinquency', ['--grace-days' => 7]);

        self::assertSame(
            SubscriptionStatus::Expired->value,
            $subscription->refresh()->status,
            'grâce calculée sur due_date (10 j > 7 j) → expired'
        );
    }

    public function test_past_due_within_invoice_grace_stays_past_due(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::PastDue->value,
        ]);
        // due_date il y a 3 jours, grâce 7 j → reste past_due.
        $this->invoice($company, $subscription, [
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(3),
        ]);

        Artisan::call('billing:enforce-delinquency', ['--grace-days' => 7]);

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'grâce (7 j) pas encore écoulée depuis due_date → past_due conservé'
        );
    }

    public function test_enforce_falls_back_to_period_when_no_invoice_exists(): void
    {
        $company = $this->company();
        // Sans facture : repli sur current_period_end (comportement #5960).
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::PastDue->value,
            'current_period_end' => now()->subDays(10),
        ]);

        Artisan::call('billing:enforce-delinquency', ['--grace-days' => 7]);

        self::assertSame(
            SubscriptionStatus::Expired->value,
            $subscription->refresh()->status,
            'sans facture, la grâce se réfère à la période de l\'abonnement'
        );
    }

    // ── billing:generate-invoices ───────────────────────────────────────────

    public function test_generate_invoices_creates_sent_invoices(): void
    {
        $company = $this->company();
        $this->subscription($company, [
            'current_period_end' => now()->subDay(),
        ]);

        Artisan::call('billing:generate-invoices');

        $invoice = Invoice::query()->where('company_id', $company->id)->first();

        self::assertInstanceOf(Invoice::class, $invoice, 'une facture mensuelle est générée');
        self::assertSame(InvoiceStatus::Sent->value, $invoice->status, 'statut canonique sent (plus de pending)');
        self::assertTrue($invoice->due_date->gt(now()), 'échéance dans 30 jours');
    }
}
