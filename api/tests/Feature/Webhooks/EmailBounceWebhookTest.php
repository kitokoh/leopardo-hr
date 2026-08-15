<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2616) : fail-closed — sans secret
 * configuré, le webhook email-bounce est refusé (503) ; avec secret,
 * signature valide acceptée, invalide rejetée (400).
 */
class EmailBounceWebhookTest extends TestCase
{
    public function test_bounce_webhook_rejected_when_secret_not_configured(): void
    {
        Config::set('services.mail_bounce_webhook.secret', '');

        $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'anyone@example.com',
            'event' => 'bounce',
        ])->assertStatus(503);
    }

    public function test_bounce_webhook_accepts_valid_secret(): void
    {
        Config::set('services.mail_bounce_webhook.secret', 'shared-secret');

        $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'anyone@example.com',
            'event' => 'bounce',
        ], [
            'X-Bounce-Webhook-Secret' => 'shared-secret',
        ])->assertStatus(200);
    }

    public function test_bounce_webhook_rejects_invalid_secret(): void
    {
        Config::set('services.mail_bounce_webhook.secret', 'shared-secret');

        $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'anyone@example.com',
            'event' => 'bounce',
        ], [
            'X-Bounce-Webhook-Secret' => 'wrong-secret',
        ])->assertStatus(400);
    }
}
