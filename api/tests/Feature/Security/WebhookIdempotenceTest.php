<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\EmployeeEmailLookupService;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5444 — Idempotence persistée des webhooks entrants.
 *
 * Un événement redélivré (même `(source, event_id)`) doit être rejoué avec
 * la réponse mémorisée, SANS effet double : un seul Payment pour
 * Stripe/Chargily, une seule ligne `communication_events` pour le bounce,
 * un seul MarketingLead. Signature invalide → 400 sans effet (fail-closed).
 */
class WebhookIdempotenceTest extends TestCase
{
    use RefreshTenantDatabase;

    private const STRIPE_SECRET = 'whsec_idem_stripe_2026';

    private const CHARGILY_SECRET = 'whsec_idem_chargily_2026';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.stripe.webhook_secret' => self::STRIPE_SECRET,
            'services.chargily.webhook_secret' => self::CHARGILY_SECRET,
            'services.mail_bounce_webhook.secret' => 'idem-bounce-secret',
            'services.marketing_lead_webhook.secret' => 'idem-marketing-secret',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postStripe(array $payload): TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::STRIPE_SECRET);

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postChargily(array $payload): TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, self::CHARGILY_SECRET);

        return $this->call('POST', '/api/v1/webhooks/chargily', [], [], [], [
            'HTTP_X_CHARGILY_SIGNATURE' => 'sha256='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postBounce(array $payload): TestResponse
    {
        return $this->withHeaders([
            'X-Bounce-Webhook-Secret' => 'idem-bounce-secret',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/webhooks/email-bounce', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postLead(array $payload): TestResponse
    {
        return $this->withHeaders([
            'X-Marketing-Lead-Token' => 'idem-marketing-secret',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/marketing/leads', $payload);
    }

    /**
     * @return array{0: Company, 1: Subscription, 2: Invoice}
     */
    private function billingFixture(string $invoiceNumber = 'LEO-IDEM-TEST', ?string $stripeInvoiceId = null): array
    {
        $company = Company::factory()->create();
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
            'stripe_subscription_id' => $stripeInvoiceId !== null ? 'sub_'.substr($stripeInvoiceId, 3) : null,
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

        return [$company, $subscription, $invoice];
    }

    // ── Stripe : rejeu sans effet double ────────────────────────────────────

    public function test_stripe_replayed_event_creates_single_payment(): void
    {
        [, , $invoice] = $this->billingFixture(stripeInvoiceId: 'in_idem_1');

        $payload = [
            'id' => 'evt_idem_stripe_1',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_idem_1',
                    'amount_paid' => 9900,
                    'currency' => 'eur',
                    'charge' => 'ch_idem_1',
                ],
            ],
        ];

        $this->postStripe($payload)->assertOk()->assertJsonPath('received', true);
        $this->assertSame(1, Payment::count());
        $this->assertSame('paid', $invoice->fresh()->status);

        // Redelivrance du MÊME événement (même id) : rejoué, aucun effet double.
        $replay = $this->postStripe($payload);
        $replay->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, Payment::count());

        $this->assertDatabaseHas('webhook_events', [
            'source' => 'stripe',
            'event_id' => 'evt_idem_stripe_1',
            'response_code' => 200,
        ]);
    }

    public function test_stripe_replayed_event_without_id_dedupes_by_payload_hash(): void
    {
        $this->billingFixture(stripeInvoiceId: 'in_idem_2');

        // Payload SANS id d'événement top-level (anciens clients Stripe) : la
        // clé d'idempotence retombe sur le hash du payload.
        $payload = [
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_idem_2']],
        ];

        $this->postStripe($payload)->assertOk();
        $this->postStripe($payload)->assertOk()->assertJsonPath('replayed', true);

        $this->assertDatabaseHas('webhook_events', [
            'source' => 'stripe',
            'event_id' => hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ]);
    }

    public function test_stripe_invalid_signature_is_rejected_before_idempotence(): void
    {
        $this->billingFixture(stripeInvoiceId: 'in_idem_3');

        $body = (string) json_encode(['id' => 'evt_idem_bad', 'type' => 'invoice.paid'], JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $badSignature = hash_hmac('sha256', $timestamp.'.'.$body, 'wrong-secret');

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$badSignature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame(0, Payment::count());
        $this->assertDatabaseCount('webhook_events', 0);
    }

    // ── Chargily : rejeu sans double encaissement ───────────────────────────

    public function test_chargily_replayed_checkout_paid_creates_single_payment(): void
    {
        [, , $invoice] = $this->billingFixture(invoiceNumber: 'LEO-IDEM-CHARGILY');

        $payload = [
            'id' => 'evt_chargily_1',
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'checkout_idem_1',
                'payment_method' => 'cib',
                'metadata' => ['invoice_number' => 'LEO-IDEM-CHARGILY'],
            ],
        ];

        $this->postChargily($payload)->assertOk()->assertJsonPath('received', true);
        $this->assertSame(1, Payment::count());
        $this->assertSame('paid', $invoice->fresh()->status);

        // Redelivrance : rejoué, AUCUN second Payment (double encaissement).
        $this->postChargily($payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, Payment::count());

        $this->assertDatabaseHas('webhook_events', [
            'source' => 'chargily',
            'event_id' => 'evt_chargily_1',
            'response_code' => 200,
        ]);
    }

    // ── Email bounce : rejeu sans doublon d'audit ───────────────────────────

    public function test_email_bounce_replayed_payload_records_single_audit_event(): void
    {
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'bounce-idem@example.com',
        ]);

        $this->app->instance(
            EmployeeEmailLookupService::class,
            new class($employee) extends EmployeeEmailLookupService
            {
                public function __construct(private readonly Employee $employee) {}

                public function resolve(string $email): Employee
                {
                    return $this->employee;
                }
            }
        );

        $payload = ['email' => 'bounce-idem@example.com', 'event' => 'hard_bounce'];

        $this->postBounce($payload)->assertOk()->assertJsonPath('received', true);
        $this->assertSame(1, CommunicationEvent::count());

        $this->postBounce($payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, CommunicationEvent::count());

        $this->assertDatabaseHas('webhook_events', [
            'source' => 'email-bounce',
            'event_id' => hash('sha256', (string) json_encode($payload)),
            'response_code' => 200,
        ]);
    }

    // ── Marketing lead : rejeu sans doublon ─────────────────────────────────

    public function test_marketing_lead_replayed_payload_creates_single_lead(): void
    {
        $payload = [
            'email' => 'lead-idem@example.com',
            'external_id' => 'lead-idem-ext-1',
            'first_name' => 'Idem',
            'last_name' => 'Test',
            'form_type' => 'demo',
        ];

        $first = $this->postLead($payload);
        $first->assertStatus(201)->assertJsonPath('data.external_id', 'lead-idem-ext-1');
        $this->assertSame(1, MarketingLead::query()->count());

        // Redelivrance identique : rejoué (201), toujours un seul lead.
        $replay = $this->postLead($payload);
        $replay->assertStatus(201)->assertJsonPath('replayed', true);
        $this->assertSame(1, MarketingLead::query()->count());

        $this->assertDatabaseHas('webhook_events', [
            'source' => 'marketing-lead',
            'event_id' => hash('sha256', (string) json_encode($payload)),
            'response_code' => 201,
        ]);
    }
}
