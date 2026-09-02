<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #6555 — les buckets de throttle des webhooks entrants et des devices ZKTeco
 * ne doivent plus être partagés par IP entre tous les tenants :
 *
 * - webhooks Stripe/Chargily : clé par signature (ou gateway) — deux tenants
 *   derrière la même IP de passerelle ne se 429 plus mutuellement ;
 * - ZKTeco : bucket dédié par serial_number — des devices derrière un même
 *   NAT ne partagent plus le quota IP du bucket `api`.
 *
 * @see api/app/Providers/AppServiceProvider.php (RateLimiter::for)
 */
class WebhookThrottleTenantIsolationTest extends TestCase
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

    public function test_two_stripe_webhooks_same_ip_different_signatures_do_not_throttle(): void
    {
        // Limite basse pour prouver l'isolation : 1 requête par bucket/min.
        config(['security.rate_limits.webhooks_inbound_per_minute' => 1]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50']);

        $payload = ['type' => 'payment_intent.succeeded'];

        // 1er webhook (signature A) : bucket sig-A consommé.
        $this->withHeader('Stripe-Signature', 't=1700000000,v1=siga');
        $first = $this->postJson('/api/v1/webhooks/stripe', $payload);
        $this->assertNotSame(429, $first->status());

        // 2e webhook d'un AUTRE tenant (signature B) depuis la même IP :
        // bucket distinct → aucun 429 illégitime.
        $this->withHeader('Stripe-Signature', 't=1700000000,v1=sigb');
        $second = $this->postJson('/api/v1/webhooks/stripe', $payload);
        $this->assertNotSame(429, $second->status());

        // Rejeu de la signature A : bucket sig-A épuisé → 429 (le throttle
        // reste actif contre les rejeux/attaques).
        $this->withHeader('Stripe-Signature', 't=1700000000,v1=siga');
        $third = $this->postJson('/api/v1/webhooks/stripe', $payload);
        $this->assertSame(429, $third->status());
    }

    public function test_two_zkteco_serials_same_ip_do_not_throttle(): void
    {
        config(['security.rate_limits.zkteco_per_minute' => 1]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60']);

        // Deux devices différents derrière le même NAT : buckets distincts.
        $first = $this->postJson('/api/v1/zkteco/heartbeat/NAT-SERIAL-1', []);
        $this->assertNotSame(429, $first->status());

        $second = $this->postJson('/api/v1/zkteco/heartbeat/NAT-SERIAL-2', []);
        $this->assertNotSame(429, $second->status());

        // Même serial : quota du device consommé → 429.
        $third = $this->postJson('/api/v1/zkteco/heartbeat/NAT-SERIAL-1', []);
        $this->assertSame(429, $third->status());
    }
}
