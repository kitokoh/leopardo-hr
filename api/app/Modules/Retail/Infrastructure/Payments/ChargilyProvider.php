<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Payments;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderInterface;
use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Modules\Retail\Domain\Exceptions\RetailPaymentProviderException;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Payments\RetailPaymentIntentResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentRefundResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider Chargily Pay v2 du paiement marketplace Retail (BC-17 RETAIL,
 * #7812) — mobile money (EDAHABIA) et carte (CIB) en Algerie.
 *
 * Meme integration que la passerelle Accounting #5272 (dev.chargily.com) :
 * POST /api/v2/checkouts (auth Bearer, montant en minor units DZD), webhook
 * signe HMAC-SHA256 du corps BRUT dans l'en-tete `signature`. La reference
 * LOCALE de l'intent voyage dans `metadata.intent_reference` : le webhook
 * et la reconciliation se rapprochent sans etat cote PSP.
 *
 * Credentials par env uniquement (`RETAIL_PAY_CHARGILY_SECRET`, cf.
 * config/retail.php) — la resolution PAR TENANT arrivera avec BC-21
 * (PR #7732, non merge). Signature fail-closed (#2615) : secret absent =
 * rejet.
 */
final class ChargilyProvider implements RetailPaymentProviderInterface
{
    private const BASE_URL = 'https://pay.chargily.net';

    public function providerCode(): string
    {
        return 'chargily';
    }

    public function createIntent(RetailOnlinePaymentIntent $intent): RetailPaymentIntentResult
    {
        $apiKey = $this->secretKey();

        if ($apiKey === '') {
            throw new RetailPaymentProviderException('Chargily provider is not configured (missing secret key).');
        }

        $returnUrl = (string) config('retail.payments.return_url');
        $successUrl = $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?')
            .'reference='.rawurlencode($this->orderReference($intent));

        $response = Http::withToken($apiKey, 'Bearer')
            ->acceptJson()
            ->post($this->endpoint('checkouts'), [
                'amount' => $intent->amount_minor,
                'currency' => strtolower($intent->currency),
                'success_url' => $successUrl,
                'failure_url' => $successUrl,
                'webhook_endpoint' => $this->webhookUrl(),
                // Rapprochement sans etat : le webhook renvoie ces metadata,
                // `intent_reference` resout l'intent (et donc le tenant).
                'metadata' => [
                    'intent_reference' => $intent->intent_reference,
                    'order_reference' => $this->orderReference($intent),
                ],
                'locale' => 'fr',
            ]);

        if (! $response->successful()) {
            Log::error('Retail/Chargily: failed to create checkout', [
                'status' => $response->status(),
                'intent_reference' => $intent->intent_reference,
            ]);

            throw new RetailPaymentProviderException('Chargily checkout creation failed.');
        }

        $id = $response->json('id');
        $url = $response->json('checkout_url');

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            Log::error('Retail/Chargily: malformed checkout response', [
                'intent_reference' => $intent->intent_reference,
            ]);

            throw new RetailPaymentProviderException('Chargily checkout response is malformed.');
        }

        /** @var array<string, mixed> $body */
        $body = is_array($response->json()) ? $response->json() : [];

        return new RetailPaymentIntentResult(
            checkoutUrl: $url,
            providerReference: $id,
            payload: ['checkout' => $body],
        );
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $secret = $this->webhookSecret();

        if ($secret === '') {
            // #2615 fail-closed : secret absent = webhook non verifiable =
            // rejet (jamais de fail-open sur une signature).
            Log::error('Retail/Chargily: webhook secret not configured — webhook rejected (fail-closed).');

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
        $checkout = is_array($data['data'] ?? null) ? $data['data'] : [];
        $metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];

        $status = match ($type) {
            'checkout.paid' => RetailPaymentIntentStatus::Succeeded,
            'checkout.failed', 'checkout.canceled' => RetailPaymentIntentStatus::Failed,
            'checkout.expired' => RetailPaymentIntentStatus::Expired,
            default => null,
        };

        $reference = $metadata['intent_reference'] ?? null;

        return new RetailPaymentWebhookEvent(
            intentReference: is_string($reference) && $reference !== '' ? $reference : null,
            status: $status,
            amountMinor: is_numeric($checkout['amount'] ?? null) ? (int) $checkout['amount'] : null,
            currency: is_string($checkout['currency'] ?? null) ? strtoupper($checkout['currency']) : null,
            raw: $data,
        );
    }

    public function verifyIntent(RetailOnlinePaymentIntent $intent): ?RetailPaymentIntentStatus
    {
        $apiKey = $this->secretKey();

        $payload = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $checkout = is_array($payload['checkout'] ?? null) ? $payload['checkout'] : [];
        $checkoutId = is_string($checkout['id'] ?? null) ? $checkout['id'] : '';

        if ($apiKey === '' || $checkoutId === '') {
            return null;
        }

        $response = Http::withToken($apiKey, 'Bearer')
            ->acceptJson()
            ->get($this->endpoint('checkouts/'.$checkoutId));

        if (! $response->successful()) {
            Log::warning('Retail/Chargily: reconcile lookup failed', [
                'status' => $response->status(),
                'intent_reference' => $intent->intent_reference,
            ]);

            return null;
        }

        $status = $response->json('status');

        return match (is_string($status) ? $status : '') {
            'paid' => RetailPaymentIntentStatus::Succeeded,
            'failed', 'canceled' => RetailPaymentIntentStatus::Failed,
            'expired' => RetailPaymentIntentStatus::Expired,
            default => null,
        };
    }

    public function refund(RetailOnlinePaymentIntent $intent): RetailPaymentRefundResult
    {
        $apiKey = $this->secretKey();

        $payload = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $checkout = is_array($payload['checkout'] ?? null) ? $payload['checkout'] : [];
        $checkoutId = is_string($checkout['id'] ?? null) ? $checkout['id'] : '';

        if ($apiKey === '' || $checkoutId === '') {
            throw new RetailPaymentProviderException('Chargily refund is not possible (missing configuration or checkout id).');
        }

        // Chargily Pay v2 : le remboursement s'opere sur le paiement du
        // checkout. L'API expose POST /api/v2/refunds (payment_id).
        $paymentId = is_string($checkout['payment_id'] ?? null) && $checkout['payment_id'] !== ''
            ? $checkout['payment_id']
            : $checkoutId;

        $response = Http::withToken($apiKey, 'Bearer')
            ->acceptJson()
            ->post($this->endpoint('refunds'), [
                'payment_id' => $paymentId,
                'amount' => $intent->amount_minor,
                'reason' => 'requested_by_seller',
            ]);

        if (! $response->successful()) {
            Log::error('Retail/Chargily: refund failed', [
                'status' => $response->status(),
                'intent_reference' => $intent->intent_reference,
            ]);

            throw new RetailPaymentProviderException('Chargily refund failed.');
        }

        /** @var array<string, mixed> $body */
        $body = is_array($response->json()) ? $response->json() : [];

        return new RetailPaymentRefundResult(succeeded: true, payload: ['refund' => $body]);
    }

    private function secretKey(): string
    {
        return (string) config('retail.payments.chargily.secret_key');
    }

    private function webhookSecret(): string
    {
        return (string) config('retail.payments.chargily.webhook_secret');
    }

    private function endpoint(string $resource): string
    {
        $base = (string) config('retail.payments.chargily.mode') === 'test'
            ? self::BASE_URL.'/test'
            : self::BASE_URL;

        return $base.'/api/v2/'.$resource;
    }

    private function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/public/market/payments/webhook/chargily';
    }

    private function orderReference(RetailOnlinePaymentIntent $intent): string
    {
        $order = $intent->order;

        return $order !== null ? $order->reference : $intent->intent_reference;
    }
}
