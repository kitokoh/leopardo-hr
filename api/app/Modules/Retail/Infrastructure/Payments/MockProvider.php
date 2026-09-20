<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Payments;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderInterface;
use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Payments\RetailPaymentIntentResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentRefundResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentWebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Provider SIMULE du paiement marketplace Retail (BC-17 RETAIL, #7812) —
 * tests Feature et developpement local, aucun appel reseau.
 *
 * - `createIntent` : URL de paiement fictive, aucune redirection reelle ;
 * - webhook : signature HMAC-SHA256 du corps brut (header `signature`,
 *   secret `retail.payments.mock.webhook_secret`) — FAIL-CLOSED comme les
 *   providers reels ; payload attendu
 *   `{ "type": "payment.succeeded|payment.failed|payment.expired",
 *      "data": { "intent_reference": "...", "amount_minor": 123,
 *                "currency": "DZD" } }` ;
 * - `verifyIntent` (reconciliation) : lit `mock_verify_status` pose dans
 *   `provider_payload` (les tests simulent ainsi la reponse du PSP) ;
 * - `refund` : accepte toujours.
 */
final class MockProvider implements RetailPaymentProviderInterface
{
    public function providerCode(): string
    {
        return 'mock';
    }

    public function createIntent(RetailOnlinePaymentIntent $intent): RetailPaymentIntentResult
    {
        return new RetailPaymentIntentResult(
            checkoutUrl: 'https://pay.mock.leopardo.test/checkout/'.$intent->intent_reference,
            providerReference: 'mock-'.$intent->intent_reference,
            payload: ['mock' => true],
        );
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $secret = (string) config('retail.payments.mock.webhook_secret');

        if ($secret === '') {
            // Fail-closed, meme en mode simule : un webhook non verifiable
            // est rejete (parite de comportement avec les providers reels).
            Log::warning('Retail/MockProvider: webhook secret not configured — webhook rejected (fail-closed).');

            return false;
        }

        $provided = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        if ($provided === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $provided);
    }

    public function parseWebhookEvent(string $payload): ?RetailPaymentWebhookEvent
    {
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $body = is_array($data['data'] ?? null) ? $data['data'] : [];

        $status = match ($type) {
            'payment.succeeded' => RetailPaymentIntentStatus::Succeeded,
            'payment.failed' => RetailPaymentIntentStatus::Failed,
            'payment.expired' => RetailPaymentIntentStatus::Expired,
            default => null,
        };

        $reference = $body['intent_reference'] ?? null;

        return new RetailPaymentWebhookEvent(
            intentReference: is_string($reference) && $reference !== '' ? $reference : null,
            status: $status,
            amountMinor: is_numeric($body['amount_minor'] ?? null) ? (int) $body['amount_minor'] : null,
            currency: is_string($body['currency'] ?? null) ? strtoupper($body['currency']) : null,
            raw: $data,
        );
    }

    public function verifyIntent(RetailOnlinePaymentIntent $intent): ?RetailPaymentIntentStatus
    {
        $payload = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $status = $payload['mock_verify_status'] ?? null;

        return is_string($status) ? RetailPaymentIntentStatus::tryFrom($status) : null;
    }

    public function refund(RetailOnlinePaymentIntent $intent): RetailPaymentRefundResult
    {
        return new RetailPaymentRefundResult(
            succeeded: true,
            payload: ['refund' => ['mock' => true, 'intent_reference' => $intent->intent_reference]],
        );
    }
}
