<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #2616 — fail-closed du webhook email-bounce.
 *
 * Secret non configuré → 503 (aucun payload traité sans authentification).
 * Secret configuré + signature valide → 200 · signature invalide → 400.
 */
class EmailBounceWebhookFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejected_when_secret_not_configured(): void
    {
        config()->set('services.mail_bounce_webhook.secret', '');

        $this->postJson('/api/v1/webhooks/email-bounce', ['email' => 'test@example.com'])
            ->assertStatus(503);
    }

    public function test_webhook_accepted_with_valid_secret(): void
    {
        config()->set('services.mail_bounce_webhook.secret', 'test-secret');

        $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'test@example.com',
            'event' => 'bounce',
        ], [
            'X-Bounce-Webhook-Secret' => 'test-secret',
        ])->assertOk();
    }

    public function test_webhook_rejected_with_invalid_secret(): void
    {
        config()->set('services.mail_bounce_webhook.secret', 'test-secret');

        $this->postJson('/api/v1/webhooks/email-bounce', ['email' => 'test@example.com'], [
            'X-Bounce-Webhook-Secret' => 'wrong-secret',
        ])->assertStatus(400);
    }
}
