<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Domain\Models\MarketingLead;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-MKT-007 — Funnel CRM marketing.
 *
 * The public vitrine's Next.js API routes call POST /api/v1/marketing/leads
 * server-to-server right after logging + best-effort forwarding a lead to
 * external CRM/email webhooks, so every signup/demo/contact/newsletter lead
 * is durably persisted regardless of whether those webhooks are configured
 * or reachable.
 */
class MarketingLeadControllerTest extends TestCase
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

    public function test_it_persists_a_signup_lead(): void
    {
        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_123_abc',
            'type' => 'signup',
            'email' => 'prospect@example.test',
            'locale' => 'fr',
            'country' => 'DZ',
            'page' => '/signup',
            'source' => 'signup_form',
            'campaign' => 'spring_launch',
            'ip' => '203.0.113.10',
            'referrer' => 'https://google.com',
            'payload' => ['company' => 'Acme SARL', 'role' => 'founder'],
            'crm_forwarded' => true,
            'email_forwarded' => true,
            'captured_at' => '2026-07-26T10:00:00Z',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.external_id', 'signup_123_abc')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('marketing_leads', [
            'external_id' => 'signup_123_abc',
            'type' => 'signup',
            'email' => 'prospect@example.test',
            'source' => 'signup_form',
            'campaign' => 'spring_launch',
            'status' => 'new',
            'crm_forwarded' => true,
            'email_forwarded' => true,
        ]);
    }

    public function test_it_is_idempotent_on_external_id(): void
    {
        $payload = [
            'external_id' => 'newsletter_456_def',
            'type' => 'newsletter',
            'email' => 'reader@example.test',
        ];

        $this->postJson('/api/v1/marketing/leads', $payload)->assertCreated();
        $this->postJson('/api/v1/marketing/leads', $payload)->assertCreated();

        $this->assertSame(1, MarketingLead::query()->where('external_id', 'newsletter_456_def')->count());
    }

    public function test_it_rejects_an_invalid_type(): void
    {
        $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'bogus_1',
            'type' => 'not_a_real_type',
            'email' => 'someone@example.test',
        ])->assertUnprocessable();
    }

    public function test_it_rejects_an_invalid_shared_secret(): void
    {
        config()->set('services.marketing_lead_webhook.secret', 'super-secret');

        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'contact_789',
            'type' => 'contact',
            'email' => 'someone@example.test',
        ], ['X-Marketing-Lead-Token' => 'wrong-secret']);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('marketing_leads', ['external_id' => 'contact_789']);
    }

    public function test_it_accepts_a_valid_shared_secret_via_bearer_token(): void
    {
        config()->set('services.marketing_lead_webhook.secret', 'super-secret');

        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'demo_request_321',
            'type' => 'demo_request',
            'email' => 'someone@example.test',
        ], ['Authorization' => 'Bearer super-secret']);

        $response->assertCreated();
        $this->assertDatabaseHas('marketing_leads', ['external_id' => 'demo_request_321']);
    }
}
