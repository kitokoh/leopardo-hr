<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
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

    public function test_stripe_invoice_paid_webhook(): void
    {
        $company = Company::factory()->create();

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-2026-0001',
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'stripe_invoice_id' => 'in_test_123',
        ]);

        $response = $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_test_123',
                    'amount_paid' => 9900,
                    'currency' => 'eur',
                ],
            ],
        ]);

        $response->assertOk();
    }

    public function test_stripe_invalid_event_type(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'unknown.event',
            'data' => ['object' => []],
        ]);

        $response->assertOk();
    }

    public function test_chargily_checkout_paid_webhook(): void
    {
        $company = Company::factory()->create();

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-2026-0002',
            'amount' => 29.00,
            'currency' => 'DZD',
            'total' => 29.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/webhooks/chargily', [
            'type' => 'checkout.paid',
            'data' => [
                'amount' => 2900,
                'metadata' => [
                    'invoice_number' => 'LEO-2026-0002',
                ],
            ],
        ]);

        $response->assertOk();
    }

    public function test_chargily_invalid_event(): void
    {
        $response = $this->postJson('/api/v1/webhooks/chargily', [
            'type' => 'unknown.event',
            'data' => [],
        ]);

        $response->assertOk();
    }
}
