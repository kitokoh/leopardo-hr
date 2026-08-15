<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Billing\Infrastructure\Services\StripeService;
use App\Modules\Billing\Infrastructure\Services\ChargilyService;
use Tests\TestCase;
use ReflectionProperty;

/**
 * Issues #2614/#2615 — fail-closed des webhooks de paiement.
 *
 * Un secret de webhook NON CONFIGURÉ doit REJETER le payload (null),
 * jamais l'accepter en « skippant » la vérification (fail-open = toute
 * signature est acceptée en prod si le secret manque).
 */
class PaymentWebhookFailClosedTest extends TestCase
{
    private function setSecret(object $service, string $secret): void
    {
        $prop = new ReflectionProperty($service, 'webhookSecret');
        $prop->setAccessible(true);
        $prop->setValue($service, $secret);
    }

    private function validStripeSignature(string $payload, string $secret): string
    {
        $timestamp = (string) time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public function test_stripe_rejects_when_secret_missing(): void
    {
        $service = new StripeService;
        $this->setSecret($service, '');

        $this->assertNull(
            $service->verifyWebhookSignature('{"type":"checkout.session.completed"}', 't=1,v1=abc'),
            'Secret Stripe absent → le webhook doit être REJETÉ (fail-closed, #2614).'
        );
    }

    public function test_stripe_accepts_valid_signature(): void
    {
        $service = new StripeService;
        $secret = 'whsec_test_123';
        $this->setSecret($service, $secret);

        $payload = '{"type":"checkout.session.completed","data":{"object":{"id":"cs_1"}}}';
        $sig = $this->validStripeSignature($payload, $secret);

        $result = $service->verifyWebhookSignature($payload, $sig);
        $this->assertIsArray($result);
        $this->assertSame('checkout.session.completed', $result['type']);
    }

    public function test_stripe_rejects_mismatched_signature(): void
    {
        $service = new StripeService;
        $this->setSecret($service, 'whsec_test_123');

        $payload = '{"type":"checkout.session.completed"}';

        $this->assertNull($service->verifyWebhookSignature($payload, 't='.time().',v1=invalid'));
    }

    public function test_chargily_rejects_when_secret_missing(): void
    {
        $service = new ChargilyService;
        $this->setSecret($service, '');

        $this->assertNull(
            $service->verifyWebhookSignature('{"type":"checkout.paid"}', 'sha256=abc'),
            'Secret Chargily absent → le webhook doit être REJETÉ (fail-closed, #2615).'
        );
    }

    public function test_chargily_accepts_valid_signature(): void
    {
        $service = new ChargilyService;
        $secret = 'test_secret';
        $this->setSecret($service, $secret);

        $payload = '{"type":"checkout.paid","data":{}}';
        $sig = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $result = $service->verifyWebhookSignature($payload, $sig);
        $this->assertIsArray($result);
        $this->assertSame('checkout.paid', $result['type']);
    }

    public function test_chargily_rejects_mismatched_signature(): void
    {
        $service = new ChargilyService;
        $this->setSecret($service, 'test_secret');

        $this->assertNull($service->verifyWebhookSignature('{"type":"checkout.paid"}', 'sha256=invalid'));
    }
}
