<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\EmployeeEmailLookupService;
use App\Shared\Services\InboundWebhookVerifier;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5740 (CRM PRE) — threat model des webhooks et intégrations.
 *
 * Les webhooks entrants sont traités comme une frontière hostile. Ces tests
 * couvrent les contrôles d'entrée ajoutés sur les endpoints à secret partagé
 * (email-bounce, marketing-lead) : taille bornée, JSON valide, fenêtre de
 * rejeu — en plus de la signature (tests existants #2616/#3888) et de
 * l'idempotence persistée (WebhookIdempotenceTest, #5444).
 */
class WebhookThreatModelTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config()->set('services.mail_bounce_webhook.secret', 'test-bounce-secret');
        $this->withHeader('X-Bounce-Webhook-Secret', 'test-bounce-secret');
        $this->bindLookupStub(null);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_oversized_payload_is_rejected_before_processing(): void
    {
        // JSON valide > 1 MiB : la borne de taille est vérifiée AVANT la
        // validation Laravel (le champ `reason` outrepasse sa propre limite
        // mais le contrôle 413 intervient en premier).
        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'oversized@example.test',
            'event' => 'bounce',
            'reason' => str_repeat('x', InboundWebhookVerifier::DEFAULT_MAX_PAYLOAD_BYTES + 1),
        ]);

        $response->assertStatus(413);
        $this->assertSame(0, CommunicationEvent::query()->count());
    }

    public function test_invalid_json_payload_is_rejected(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/webhooks/email-bounce',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid json',
        );

        $response->assertStatus(400);
        $this->assertSame(0, CommunicationEvent::query()->count());
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/v1/webhooks/email-bounce',
            ['email' => 'unknown@example.test', 'event' => 'bounce'],
            ['X-Webhook-Timestamp' => (string) (time() - 3_600)],
        );

        $response->assertStatus(400);
        $this->assertSame(0, CommunicationEvent::query()->count());
    }

    public function test_fresh_timestamp_is_accepted(): void
    {
        $response = $this->postJson(
            '/api/v1/webhooks/email-bounce',
            ['email' => 'unknown@example.test', 'event' => 'bounce'],
            ['X-Webhook-Timestamp' => (string) (time() - 60)],
        );

        $response->assertOk()->assertJsonPath('received', true);
    }

    public function test_oversized_payload_rejected_on_marketing_lead_endpoint(): void
    {
        config()->set('services.marketing_lead_webhook.secret', 'test-lead-secret');

        // Payload JSON valide passant la validation FormRequest mais dépassant
        // 1 MiB (champ `payload` non borné) → le contrôle rejette en 413
        // avant le registre d'idempotence.
        $raw = json_encode([
            'external_id' => 'lead-oversized',
            'type' => 'signup',
            'email' => 'lead@example.test',
            'payload' => ['note' => str_repeat('x', InboundWebhookVerifier::DEFAULT_MAX_PAYLOAD_BYTES + 1)],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postJson(
            '/api/v1/marketing/leads',
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
            ['Authorization' => 'Bearer test-lead-secret'],
        );

        $response->assertStatus(413);
    }

    public function test_invalid_json_rejected_on_marketing_lead_endpoint(): void
    {
        config()->set('services.marketing_lead_webhook.secret', 'test-lead-secret');

        // JSON illisible : la validation FormRequest échoue avant le corps du
        // contrôleur → 422 (comportement Laravel standard, aucun effet de bord).
        $response = $this->call(
            'POST',
            '/api/v1/marketing/leads',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-lead-secret',
            ],
            '{not json',
        );

        $response->assertStatus(422);
    }

    private function bindLookupStub(?Employee $employee): void
    {
        $this->app->bind(EmployeeEmailLookupService::class, fn () => new class($employee) extends EmployeeEmailLookupService
        {
            public function __construct(private readonly ?Employee $stubbed) {}

            public function resolve(string $email): ?Employee
            {
                return $this->stubbed;
            }
        });
    }
}
