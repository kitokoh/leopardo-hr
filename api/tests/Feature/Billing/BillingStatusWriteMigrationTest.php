<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6246) — toutes les écritures de statut de souscription passent
 * par la machine à états (SubscriptionStatus + Subscription::transitionTo()).
 *
 * Couvre les handlers StripeService (webhooks providers) et les endpoints
 * manager (upgrade/cancel/renew) : plus aucun `update(['status' => ...])`
 * direct sur Subscription hors de la machine à états.
 *
 * Règle « cancelled est sticky » : un écho webhook ne réactive jamais une
 * souscription résiliée localement ; seule une réactivation explicite
 * (checkout, renew, upgrade) le permet.
 */
class BillingStatusWriteMigrationTest extends TestCase
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

    private function invoice(Company $company, Subscription $subscription, string $stripeInvoiceId): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-'.uniqid(),
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'sent',
            'due_date' => now()->addDays(10),
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function stripeEvent(string $type, array $object): void
    {
        (new StripeService)->handleEvent([
            'type' => $type,
            'data' => ['object' => $object],
        ]);
    }

    // ── invoice.paid ─────────────────────────────────────────────────────────

    public function test_invoice_paid_transitions_past_due_subscription_to_active(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::PastDue->value,
            'stripe_subscription_id' => 'sub_past_due',
        ]);
        $this->invoice($company, $subscription, 'in_paid');

        $this->stripeEvent('invoice.paid', [
            'id' => 'in_paid',
            'amount_paid' => 9900,
            'currency' => 'eur',
            'charge' => 'ch_paid',
            'subscription' => 'sub_past_due',
            'period_start' => now()->getTimestamp(),
            'period_end' => now()->addMonth()->getTimestamp(),
        ]);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'invoice.paid → transition gardée past_due → active'
        );
    }

    public function test_invoice_paid_does_not_reactivate_locally_cancelled_subscription(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => now()->subDay(),
            'stripe_subscription_id' => 'sub_cancelled',
        ]);
        $this->invoice($company, $subscription, 'in_late');

        $this->stripeEvent('invoice.paid', [
            'id' => 'in_late',
            'amount_paid' => 9900,
            'currency' => 'eur',
            'charge' => 'ch_late',
            'subscription' => 'sub_cancelled',
        ]);

        self::assertSame(
            SubscriptionStatus::Cancelled->value,
            $subscription->refresh()->status,
            'un écho invoice.paid ne réactive pas une souscription résiliée localement'
        );
    }

    // ── invoice.payment_failed ───────────────────────────────────────────────

    public function test_invoice_payment_failed_transitions_active_to_past_due(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company);
        $this->invoice($company, $subscription, 'in_fail');

        $this->stripeEvent('invoice.payment_failed', ['id' => 'in_fail']);

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'invoice.payment_failed → past_due (transition gardée)'
        );
    }

    // ── customer.subscription.updated ────────────────────────────────────────

    public function test_subscription_updated_unpaid_maps_to_past_due(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, ['stripe_subscription_id' => 'sub_unpaid']);

        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_unpaid',
            'status' => 'unpaid',
        ]);

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'Stripe unpaid (n\'existe pas côté Leopardo) → past_due, plus jamais une écriture invalide'
        );
    }

    public function test_subscription_updated_canceled_maps_to_cancelled(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, ['stripe_subscription_id' => 'sub_canceled']);

        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_canceled',
            'status' => 'canceled',
        ]);

        self::assertSame(
            SubscriptionStatus::Cancelled->value,
            $subscription->refresh()->status,
            'Stripe canceled → cancelled (transition gardée)'
        );
    }

    public function test_subscription_updated_incomplete_expired_maps_to_expired(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, ['stripe_subscription_id' => 'sub_incomplete']);

        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_incomplete',
            'status' => 'incomplete_expired',
        ]);

        self::assertSame(
            SubscriptionStatus::Expired->value,
            $subscription->refresh()->status,
            'checkout jamais complété → expired'
        );
    }

    public function test_subscription_updated_unknown_status_keeps_local_state(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Active->value,
            'stripe_subscription_id' => 'sub_unknown',
        ]);

        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_unknown',
            'status' => 'paused',
        ]);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'un statut Stripe inconnu conserve l\'état local'
        );
    }

    public function test_subscription_updated_active_does_not_reactivate_locally_cancelled(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => now()->subDay(),
            'stripe_subscription_id' => 'sub_echo_active',
        ]);

        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_echo_active',
            'status' => 'active',
        ]);

        self::assertSame(
            SubscriptionStatus::Cancelled->value,
            $subscription->refresh()->status,
            'un écho subscription.updated=active ne réactive pas une souscription résiliée localement'
        );
    }

    // ── customer.subscription.deleted ────────────────────────────────────────

    public function test_subscription_deleted_transitions_to_cancelled_and_suspends_company(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, ['stripe_subscription_id' => 'sub_deleted']);

        $this->stripeEvent('customer.subscription.deleted', ['id' => 'sub_deleted']);

        self::assertSame(
            SubscriptionStatus::Cancelled->value,
            $subscription->refresh()->status,
            'customer.subscription.deleted → cancelled'
        );
        self::assertSame(
            'suspended',
            $company->refresh()->status,
            'l\'enforcement opérationnel est porté par companies.status'
        );
    }

    // ── checkout.session.completed ───────────────────────────────────────────

    public function test_checkout_completed_creates_active_subscription(): void
    {
        $company = $this->company();

        $this->stripeEvent('checkout.session.completed', [
            'metadata' => ['company_id' => (string) $company->id, 'plan' => 'operations'],
            'subscription' => 'sub_new',
            'customer' => 'cus_new',
        ]);

        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->first();

        self::assertInstanceOf(Subscription::class, $subscription, 'checkout complet → souscription créée');
        self::assertSame(SubscriptionStatus::Active->value, $subscription->status);
        self::assertSame('operations', $subscription->plan);
        self::assertSame('active', $company->refresh()->status);
    }

    public function test_checkout_completed_reactivates_expired_subscription(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Expired->value,
            'current_period_end' => now()->subMonth(),
        ]);

        $this->stripeEvent('checkout.session.completed', [
            'metadata' => ['company_id' => (string) $company->id, 'plan' => 'pilot'],
            'subscription' => 'sub_reactivate',
            'customer' => 'cus_rtv',
        ]);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'un nouvel abonnement réactive une souscription expirée'
        );
    }

    public function test_checkout_completed_reactivates_locally_cancelled_subscription(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => now()->subDay(),
        ]);

        $this->stripeEvent('checkout.session.completed', [
            'metadata' => ['company_id' => (string) $company->id, 'plan' => 'enterprise'],
            'subscription' => 'sub_rtv2',
            'customer' => 'cus_rtv2',
        ]);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'un NOUVEAU checkout est une réactivation explicite → cancelled → active'
        );
    }

    // ── idempotence à statut courant ─────────────────────────────────────────

    public function test_transition_to_current_status_is_idempotent(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, ['stripe_subscription_id' => 'sub_same']);

        // Rejoué : statut identique → aucune exception, période rafraîchie.
        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_same',
            'status' => 'active',
        ]);
        $this->stripeEvent('customer.subscription.updated', [
            'id' => 'sub_same',
            'status' => 'active',
        ]);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'une transition vers le statut courant est un no-op idempotent'
        );
    }

    // ── endpoints manager (upgrade / cancel / renew) ─────────────────────────

    public function test_upgrade_transitions_past_due_to_active(): void
    {
        $company = $this->company();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);
        assert($manager instanceof \App\Core\Auth\Domain\Models\Employee);
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::PastDue->value,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/subscription/upgrade', [
            'plan' => 'enterprise',
            'payment_method' => 'stripe',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'upgrade passe par la machine à états (past_due → active)'
        );
    }

    public function test_cancel_twice_is_idempotent(): void
    {
        $company = $this->company();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);
        assert($manager instanceof \App\Core\Auth\Domain\Models\Employee);
        $subscription = $this->subscription($company);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/subscription/cancel', ['reason' => 'Pause'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson('/api/v1/billing/subscription/cancel', ['reason' => 'Pause confirmée'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        self::assertSame(
            SubscriptionStatus::Cancelled->value,
            $subscription->refresh()->status,
            'résilier deux fois est idempotent (pas de transition invalide)'
        );
    }

    public function test_renew_reactivates_cancelled_subscription(): void
    {
        $company = $this->company();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);
        assert($manager instanceof \App\Core\Auth\Domain\Models\Employee);
        $subscription = $this->subscription($company, [
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => now()->subDay(),
            'cancel_reason' => 'Pause client',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/subscription/renew')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_reason', null);

        self::assertSame(
            SubscriptionStatus::Active->value,
            $subscription->refresh()->status,
            'renew est la réactivation explicite (cancelled → active)'
        );
    }
}
