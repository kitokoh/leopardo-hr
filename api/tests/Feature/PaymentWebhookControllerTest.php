<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Billing\Domain\Models\Subscription;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private const STRIPE_SECRET = 'whsec_test_stripe_2026';
    private const CHARGILY_SECRET = 'whsec_test_chargily_2026';

    protected function setUp(): void
    {
        parent::setUp();

        // #2614/#2615 : les services webhook sont fail-closed — un secret est
        // requis pour vérifier les signatures. Les tests signent donc les
        // payloads avec un secret de test.
        config([
            'services.stripe.webhook_secret' => self::STRIPE_SECRET,
            'services.chargily.webhook_secret' => self::CHARGILY_SECRET,
        ]);
    }

    private function postStripeWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::STRIPE_SECRET);

        // NB : `call()` n'applique pas les defaultHeaders — les en-têtes
        // doivent passer par le tableau `$server` (préfixe HTTP_).
        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function postChargilyWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, self::CHARGILY_SECRET);

        return $this->call('POST', '/api/v1/webhooks/chargily', [], [], [], [
            'HTTP_X_CHARGILY_SIGNATURE' => 'sha256='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_stripe_invoice_paid_marks_invoice_paid_and_records_payment(): void
    {
        [$company, $subscription, $invoice] = $this->billingFixture(stripeInvoiceId: 'in_123');

        $response = $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_123',
                    'amount_paid' => 12500,
                    'currency' => 'eur',
                    'charge' => 'ch_123',
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('stripe', $invoice->fresh()->payment_method);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'amount' => 125.00,
            'currency' => 'EUR',
            'method' => 'card',
            'provider_reference' => 'ch_123',
            'status' => 'completed',
        ]);
    }

    public function test_stripe_payment_failed_marks_invoice_and_subscription_past_due(): void
    {
        [, $subscription, $invoice] = $this->billingFixture(stripeInvoiceId: 'in_failed');

        $this->postStripeWebhook([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_failed']],
        ])->assertOk();

        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_stripe_subscription_deleted_cancels_subscription(): void
    {
        [, $subscription] = $this->billingFixture(stripeSubscriptionId: 'sub_cancelled');

        $this->postStripeWebhook([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_cancelled']],
        ])->assertOk();

        $fresh = $subscription->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_chargily_checkout_paid_marks_invoice_paid_and_records_payment(): void
    {
        [$company, , $invoice] = $this->billingFixture(invoiceNumber: 'LEO-CHARGILY-1');

        $this->postChargilyWebhook([
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'checkout_123',
                'payment_method' => 'cib',
                'metadata' => ['invoice_number' => 'LEO-CHARGILY-1'],
            ],
        ])->assertOk()->assertJsonPath('received', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('chargily', $invoice->fresh()->payment_method);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'amount' => 99.00,
            'currency' => 'EUR',
            'method' => 'cib',
            'provider_reference' => 'checkout_123',
            'status' => 'completed',
        ]);
    }

    public function test_invalid_stripe_payload_is_acknowledged_without_side_effects(): void
    {
        [, $subscription, $invoice] = $this->billingFixture(stripeInvoiceId: 'in_safe');

        $response = $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 'in_unknown']],
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_unknown_stripe_event_is_acknowledged_without_side_effects(): void
    {
        [, $subscription, $invoice] = $this->billingFixture(stripeInvoiceId: 'in_safe');

        $response = $this->postStripeWebhook([
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_123']],
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_invalid_chargily_payload_is_acknowledged_without_side_effects(): void
    {
        [, $subscription, $invoice] = $this->billingFixture(invoiceNumber: 'LEO-CHARGILY-SAFE');

        $response = $this->postChargilyWebhook([
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'checkout_unknown',
                'payment_method' => 'cib',
                'metadata' => ['invoice_number' => 'LEO-UNKNOWN'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    /**
     * @return array{0: Company, 1: Subscription, 2: Invoice}
     */
    private function billingFixture(
        string $invoiceNumber = 'LEO-2026-TEST',
        ?string $stripeInvoiceId = null,
        ?string $stripeSubscriptionId = null,
    ): array {
        $company = Company::factory()->create();
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => $invoiceNumber,
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'sent',
            'due_date' => now()->addDays(10),
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        $this->assertSame(0, Payment::count());

        return [$company, $subscription, $invoice];
    }

    public function test_chargily_webhook_rejects_invalid_signature(): void
    {
        [$company, , $invoice] = $this->billingFixture(invoiceNumber: 'LEO-SIG-TEST');

        $response = $this->withHeaders([
            'X-Chargily-Signature' => 'sha256=invalidsignature',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/webhooks/chargily', [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'checkout_fake',
                'payment_method' => 'cib',
                'metadata' => ['invoice_number' => 'LEO-SIG-TEST'],
            ],
        ]);

        // #2615 : secret de test configuré → signature invalide → 400 déterministe.
        $response->assertStatus(400);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        [$company, , $invoice] = $this->billingFixture(stripeInvoiceId: 'in_sig_test');

        $response = $this->withHeaders([
            'Stripe-Signature' => 't=9999999999,v1=invalidsignature',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/webhooks/stripe', [
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 'in_sig_test']],
        ]);

        // #2614 : secret de test configuré → signature invalide → 400 déterministe.
        $response->assertStatus(400);
    }
}

