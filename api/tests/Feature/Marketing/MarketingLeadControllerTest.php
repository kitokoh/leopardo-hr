<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
 * Authentification par secret partagé quand `services.marketing_lead_webhook.secret`
 * est configuré (secret invalide → 400, aucune écriture, #3888).
 *
 * #7301 — secret NON configuré : l'ingestion reste acceptée et le lead est
 * PERSISTÉ (ne jamais perdre un lead d'acquisition) mais une alerte est émise
 * (log `critical` + relais optionnel `MARKETING_ALERT_WEBHOOK_URL`) : c'est le
 * test de non-régression de la perte de leads constatée en production (503
 * MARKETING_WEBHOOK_NOT_CONFIGURED + aucune écriture).
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

    /**
     * #7301 — non-régression (data-loss BC-11 CRM) : quand le secret partagé
     * n'est PAS configuré, le lead doit être persisté malgré tout. Avant ce
     * correctif l'endpoint répondait 503 MARKETING_WEBHOOK_NOT_CONFIGURED et
     * `marketing_leads` restait vide — le lead d'inscription était perdu.
     */
    public function test_it_persists_the_lead_when_the_shared_secret_is_not_configured(): void
    {
        config()->set('services.marketing_lead_webhook.secret', '');

        $response = $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_no_secret_001',
            'type' => 'signup',
            'email' => 'prospect@example.test',
            'locale' => 'fr',
            'country' => 'DZ',
            'source' => 'signup_form',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.external_id', 'signup_no_secret_001')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('marketing_leads', [
            'external_id' => 'signup_no_secret_001',
            'type' => 'signup',
            'email' => 'prospect@example.test',
            'source' => 'signup_form',
            'status' => 'new',
        ]);
    }

    /**
     * #7301 — l'absence de configuration ne doit jamais être silencieuse :
     * elle émet une alerte de niveau critique.
     */
    public function test_it_alerts_when_the_shared_secret_is_not_configured(): void
    {
        config()->set('services.marketing_lead_webhook.secret', '');

        /** @var array<int, MessageLogged> $logged */
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_no_secret_002',
            'type' => 'signup',
            'email' => 'prospect@example.test',
        ])->assertCreated();

        $alerts = array_values(array_filter(
            $logged,
            fn (MessageLogged $event): bool => $event->level === 'critical'
                && str_contains((string) $event->message, 'marketing.lead.ingest_unauthenticated'),
        ));

        $this->assertCount(1, $alerts, 'L\'ingestion sans secret doit émettre UNE alerte critique.');
    }

    /**
     * #7301 — l'alerte est relayée vers `MARKETING_ALERT_WEBHOOK_URL` quand il
     * est configuré (Slack/CRM/mail), jamais un simple log muet.
     */
    public function test_it_relays_the_alert_to_the_configured_webhook(): void
    {
        config()->set('services.marketing_lead_webhook.secret', '');
        config()->set('services.marketing_lead_webhook.alert_url', 'https://alerts.example.test/marketing');
        Http::fake();

        $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_no_secret_003',
            'type' => 'signup',
            'email' => 'prospect@example.test',
        ])->assertCreated();

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alerts.example.test/marketing'
            && $request['event'] === 'marketing.lead.ingest_unauthenticated'
            && $request['severity'] === 'critical');
    }

    /**
     * #7301 — quand le secret EST configuré, aucune alerte parasite n'est
     * émise : l'ingestion nominale reste silencieuse.
     */
    public function test_it_does_not_alert_when_the_secret_is_configured(): void
    {
        config()->set('services.marketing_lead_webhook.alert_url', 'https://alerts.example.test/marketing');
        Http::fake();

        $this->postJson('/api/v1/marketing/leads', [
            'external_id' => 'signup_with_secret_004',
            'type' => 'signup',
            'email' => 'prospect@example.test',
        ], $this->authorizedHeaders())->assertCreated();

        Http::assertNothingSent();
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
