<?php

namespace Tests\Unit\Services;

use App\Modules\Billing\Infrastructure\Services\ChargilyService;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2615) : fail-closed — sans secret
 * configuré, un webhook Chargily ne doit JAMAIS être accepté.
 */
class ChargilyServiceTest extends TestCase
{
    public function test_verify_webhook_signature_fails_closed_without_secret(): void
    {
        config()->set('services.chargily.webhook_secret', '');

        $service = new ChargilyService;

        $this->assertNull($service->verifyWebhookSignature(
            '{"type":"payment.succeeded","data":{}}',
            'sha256=forged',
        ));
    }

    public function test_verify_webhook_signature_accepts_valid_signature(): void
    {
        $secret = 'chargily_test_secret';
        config()->set('services.chargily.webhook_secret', $secret);

        $service = new ChargilyService;

        $payload = '{"type":"payment.succeeded","data":{"id":"pay_test"}}';
        $signature = hash_hmac('sha256', $payload, $secret);

        $result = $service->verifyWebhookSignature($payload, 'sha256='.$signature);

        $this->assertIsArray($result);
        $this->assertSame('payment.succeeded', $result['type']);
    }

    public function test_verify_webhook_signature_rejects_mismatch(): void
    {
        config()->set('services.chargily.webhook_secret', 'chargily_test_secret');

        $service = new ChargilyService;

        $this->assertNull($service->verifyWebhookSignature(
            '{"type":"payment.succeeded"}',
            'sha256=forged-signature',
        ));
    }
}
