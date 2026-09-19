<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\InvoicePaymentReceiptMail;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
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

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postStripeWebhook(array $payload): TestResponse
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

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postChargilyWebhook(array $payload): TestResponse
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
        ?string $period = null,
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
            'period' => $period,
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

    // ── #7763 — rapprochement Stripe (stripe_invoice_id) + reçu de paiement ──

    public function test_stripe_invoice_created_reconciles_internal_invoice(): void
    {
        [, , $invoice] = $this->billingFixture(
            stripeSubscriptionId: 'sub_rec_created',
            period: now()->format('Y-m'),
        );

        $this->assertNull($invoice->stripe_invoice_id);

        $this->postStripeWebhook([
            'id' => 'evt_invoice_created_1',
            'type' => 'invoice.created',
            'data' => [
                'object' => [
                    'id' => 'in_rec_created',
                    'subscription' => 'sub_rec_created',
                    'period_start' => now()->timestamp,
                ],
            ],
        ])->assertOk()->assertJsonPath('received', true);

        $fresh = $invoice->refresh();
        // stripe_invoice_id écrit — le statut interne ne bouge PAS sur created.
        $this->assertSame('in_rec_created', $fresh->stripe_invoice_id);
        $this->assertSame('sent', $fresh->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_stripe_invoice_paid_reconciles_by_subscription_and_period_then_marks_paid(): void
    {
        Mail::fake();

        [$company, $subscription, $invoice] = $this->billingFixture(
            stripeSubscriptionId: 'sub_rec_paid',
            period: now()->format('Y-m'),
        );
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $payload = [
            'id' => 'evt_invoice_paid_rec_1',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_rec_paid',
                    'subscription' => 'sub_rec_paid',
                    'period_start' => now()->timestamp,
                    'amount_paid' => 9900,
                    'currency' => 'eur',
                    'charge' => 'ch_rec_paid',
                ],
            ],
        ];

        $this->postStripeWebhook($payload)->assertOk()->assertJsonPath('received', true);

        $fresh = $invoice->refresh();
        $this->assertSame('in_rec_paid', $fresh->stripe_invoice_id);
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('stripe', $fresh->payment_method);
        $this->assertSame(1, Payment::count());

        // Reçu de paiement au principal (point unique transitionTo → event).
        Mail::assertQueued(
            InvoicePaymentReceiptMail::class,
            fn (InvoicePaymentReceiptMail $mail): bool => $mail->hasTo($principal->email)
        );

        // Rejeu idempotent (#5444) : réponse mémorisée, zéro double paiement
        // ni second reçu (la transition paid → paid ne re-dispatch pas).
        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('replayed', true);

        $this->assertSame(1, Payment::count());
        Mail::assertQueued(InvoicePaymentReceiptMail::class, 1);
    }

    public function test_chargily_checkout_paid_queues_payment_receipt_to_principal(): void
    {
        Mail::fake();

        [$company, , $invoice] = $this->billingFixture(invoiceNumber: 'LEO-CHARGILY-RECEIPT');
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->postChargilyWebhook([
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'checkout_receipt_1',
                'payment_method' => 'cib',
                'metadata' => ['invoice_number' => 'LEO-CHARGILY-RECEIPT'],
            ],
        ])->assertOk()->assertJsonPath('received', true);

        $this->assertSame('paid', $invoice->refresh()->status);
        Mail::assertQueued(
            InvoicePaymentReceiptMail::class,
            fn (InvoicePaymentReceiptMail $mail): bool => $mail->hasTo($principal->email)
        );
        Mail::assertQueued(InvoicePaymentReceiptMail::class, 1);
    }

    public function test_stripe_invoice_paid_without_matching_internal_invoice_stays_safe(): void
    {
        Mail::fake();

        // Période DIFFÉRENTE → aucun rapprochement possible : zéro écriture,
        // zéro paiement fantôme, zéro reçu.
        [, $subscription, $invoice] = $this->billingFixture(
            stripeSubscriptionId: 'sub_rec_other',
            period: now()->subMonths(2)->format('Y-m'),
        );

        $this->postStripeWebhook([
            'id' => 'evt_invoice_paid_nomatch',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_nomatch',
                    'subscription' => 'sub_rec_other',
                    'period_start' => now()->timestamp,
                    'amount_paid' => 9900,
                ],
            ],
        ])->assertOk();

        $fresh = $invoice->refresh();
        $this->assertNull($fresh->stripe_invoice_id);
        $this->assertSame('sent', $fresh->status);
        $this->assertSame(0, Payment::count());
        Mail::assertNotQueued(InvoicePaymentReceiptMail::class);
        // La souscription Stripe correspondante est bien renouvelée (comportement
        // historique conservé : subscription active même sans facture interne).
        $this->assertSame('active', $subscription->refresh()->status);
    }
}
