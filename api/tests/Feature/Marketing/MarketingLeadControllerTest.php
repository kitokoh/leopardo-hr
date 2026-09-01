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
 *
 * The endpoint is fail-closed (#3888) : without a configured shared secret
 * it returns 503 and never ingests a payload.
 */
class MarketingLeadControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private const SECRET = 'super-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config()->set('services.marketing_lead_webhook.secret', self::SECRET);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function authorizedHeaders(): array
    {
        return ['Authorization' => 'Bearer '.self::SECRET];
    }

    public function test_it_is_fail_closed_when_secret_is_not_configured(): void
    {
        config()->set('services.marketing_lead_webhook.secret', '');

        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_poisoned_1',
            'type' => 'signup',
            'email' => 'attacker@example.test',
        ]);

        $response->assertStatus(503);
        $this->assertDatabaseMissing('marketing_leads', ['external_id' => 'signup_poisoned_1']);
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
        ], $this->authorizedHeaders());

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

        $this->postJson('/api/v1/marketing/leads', $payload, $this->authorizedHeaders())->assertCreated();
        $this->postJson('/api/v1/marketing/leads', $payload, $this->authorizedHeaders())->assertCreated();

        $this->assertSame(1, MarketingLead::query()->where('external_id', 'newsletter_456_def')->count());
    }

    public function test_it_rejects_an_invalid_type(): void
    {
        $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'bogus_1',
            'type' => 'not_a_real_type',
            'email' => 'someone@example.test',
        ], $this->authorizedHeaders())->assertUnprocessable();
    }

    public function test_it_rejects_an_invalid_shared_secret(): void
    {
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
        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'demo_request_321',
            'type' => 'demo_request',
            'email' => 'someone@example.test',
        ], $this->authorizedHeaders());

        $response->assertCreated();
        $this->assertDatabaseHas('marketing_leads', ['external_id' => 'demo_request_321']);
    }

    public function test_it_persists_a_solution_survey_lead_with_consent(): void
    {
        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'solution_survey_resto_001',
            'type' => 'solution_survey',
            'email' => 'resto.chef@example.test',
            'locale' => 'fr',
            'page' => '/restaurant',
            'source' => 'solution_survey_restaurant',
            'payload' => [
                'solution' => 'restaurant',
                'answers' => ['employee_count' => '6_20', 'attendance_device' => 'kiosk'],
                'packages' => ['mobile_employee', 'mobile_manager', 'kiosk', 'edge'],
                'consent' => true,
                'consented_at' => '2026-09-01T10:00:00Z',
            ],
        ], $this->authorizedHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.external_id', 'solution_survey_resto_001')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('marketing_leads', [
            'external_id' => 'solution_survey_resto_001',
            'type' => 'solution_survey',
            'email' => 'resto.chef@example.test',
            'source' => 'solution_survey_restaurant',
            'status' => 'new',
        ]);
    }

    public function test_it_rejects_an_unknown_lead_type(): void
    {
        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'bogus_type_001',
            'type' => 'not_a_lead_type',
            'email' => 'someone@example.test',
        ], $this->authorizedHeaders());

        $response->assertStatus(422);
        $this->assertDatabaseMissing('marketing_leads', ['external_id' => 'bogus_type_001']);
    }
}
