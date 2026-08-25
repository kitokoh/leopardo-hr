<?php

namespace Tests\Unit\Services;

use App\Modules\Billing\Infrastructure\Services\StripeService;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2614) : fail-closed — sans secret
 * configuré, un webhook Stripe ne doit JAMAIS être accepté.
 */
class StripeServiceTest extends TestCase
{
    public function test_verify_webhook_signature_fails_closed_without_secret(): void
    {
        config()->set('services.stripe.webhook_secret', '');

        $service = new StripeService;

        $this->assertNull($service->verifyWebhookSignature(
            '{"type":"checkout.session.completed","data":{}}',
            't=123,v1=abc',
        ));
    }

    public function test_verify_webhook_signature_accepts_valid_signature(): void
    {
        $secret = 'whsec_test_secret';
        config()->set('services.stripe.webhook_secret', $secret);

        $service = new StripeService;

        $payload = '{"type":"checkout.session.completed","data":{"id":"cs_test"}}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $result = $service->verifyWebhookSignature(
            $payload,
            't='.$timestamp.',v1='.$signature,
        );

        $this->assertIsArray($result);
        $this->assertSame('checkout.session.completed', $result['type']);
    }

    public function test_verify_webhook_signature_rejects_malformed_signature_header(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $service = new StripeService;

        $this->assertNull($service->verifyWebhookSignature(
            '{"type":"checkout.session.completed"}',
            'malformed-header',
        ));
    }

    public function test_verify_webhook_signature_rejects_invalid_signature(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $service = new StripeService;

        $this->assertNull($service->verifyWebhookSignature(
            '{"type":"checkout.session.completed"}',
            't='.time().',v1=forged-signature',
        ));
    }
}
