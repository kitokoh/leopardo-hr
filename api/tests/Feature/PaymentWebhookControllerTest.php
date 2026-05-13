<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
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

    public function test_stripe_invoice_paid_marks_invoice_paid_and_records_payment(): void
    {
        [$company, $subscription, $invoice] = $this->billingFixture(stripeInvoiceId: 'in_123');

        $response = $this->postJson('/api/v1/webhooks/stripe', [
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

        $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_failed']],
        ])->assertOk();

        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_stripe_subscription_deleted_cancels_subscription(): void
    {
        [, $subscription] = $this->billingFixture(stripeSubscriptionId: 'sub_cancelled');

        $this->postJson('/api/v1/webhooks/stripe', [
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

        $this->postJson('/api/v1/webhooks/chargily', [
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

        $response = $this->postJson('/api/v1/webhooks/stripe', [
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

        $response = $this->postJson('/api/v1/webhooks/stripe', [
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

        $response = $this->postJson('/api/v1/webhooks/chargily', [
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
}
