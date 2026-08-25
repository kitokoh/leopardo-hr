<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Accounting\Domain\DTOs\PaymentCheckout;
use App\Modules\Accounting\Domain\DTOs\PaymentWebhookData;
use App\Modules\Accounting\Domain\Exceptions\PaymentGatewayNotConfiguredException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * #5272 — Passerelle Stripe (Checkout Session, mode paiement unique) pour les
 * documents comptables (ADR-0017 option A : FR/UK/US/CI).
 *
 * REST sans SDK (pattern StripeService du module Billing) : POST
 * /v1/checkout/sessions ; montant en unité mineure (devises 0 décimale
 * gérées via GatewayMoney). Webhook : en-tête Stripe-Signature `t=..,v1=..`,
 * HMAC-SHA256 du corps `t.payload`.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.stripe.com';

    /** Tolérance anti-fraude sur le montant notifié (unités mineures). */
    public const AMOUNT_TOLERANCE_MINOR = 2;

    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret');
    }

    public function gatewayName(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function createCheckout(
        AccountingDocument $document,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): PaymentCheckout {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayNotConfiguredException((string) $document->company_id);
        }

        $currency = strtolower((string) ($document->currency ?? 'eur'));

        $response = Http::withToken($this->secretKey, 'Bearer')
            ->asForm()
            ->post(self::API_URL.'/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => GatewayMoney::toMinorUnits($amount, (string) ($document->currency ?? 'EUR')),
                'line_items[0][price_data][product_data][name]' => $this->productName($document),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata[document_id]' => $document->id,
                'metadata[company_id]' => (string) $document->company_id,
            ]);

        if (! $response->successful()) {
            Log::error('Stripe: failed to create checkout session', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException(__('accounting.errors.gateway_checkout_failed'));
        }

        $id = $response->json('id');
        $url = $response->json('url');
        $expiresAt = $response->json('expires_at');

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            Log::error('Stripe: malformed checkout session response', ['body' => (string) $response->body()]);

            throw new RuntimeException(__('accounting.errors.gateway_checkout_failed'));
        }

        return new PaymentCheckout(
            url: $url,
            gatewayCheckoutId: $id,
            gateway: $this->gatewayName(),
            expiresAt: is_int($expiresAt)
                ? CarbonImmutable::createFromTimestampUTC($expiresAt)
                : CarbonImmutable::now()->addHours(24),
        );
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            // #2614 fail-closed : secret absent = webhook non vérifiable = rejet.
            Log::error('Stripe: webhook secret not configured — webhook REJETÉ (fail-closed).');

            return null;
        }

        $elements = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = explode('=', trim($part), 2);
            $elements[$key] = $value;
        }

        $timestamp = (string) ($elements['t'] ?? '');
        $signature = (string) ($elements['v1'] ?? '');

        if ($timestamp === '' || $signature === '') {
            return null;
        }

        // Rejet des événements de plus de 5 minutes (anti-rejeu).
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Stripe: webhook timestamp too old', ['timestamp' => $timestamp]);

            return null;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Stripe: webhook signature mismatch');

            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?PaymentWebhookData
    {
        $type = (string) ($payload['type'] ?? '');
        $session = $payload['data']['object'] ?? null;

        if (! is_array($session)) {
            return null;
        }

        $id = (string) ($session['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $eventType = match (true) {
            $type === 'checkout.session.completed' && $paymentStatus === 'paid' => 'paid',
            $type === 'checkout.session.completed' => 'other',
            $type === 'checkout.session.expired' => 'cancelled',
            default => 'other',
        };

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];

        return new PaymentWebhookData(
            gatewayPaymentId: $id,
            amountMinor: (int) ($session['amount_total'] ?? 0),
            currency: strtoupper((string) ($session['currency'] ?? 'eur')),
            eventType: $eventType,
            documentId: isset($metadata['document_id']) ? (int) $metadata['document_id'] : null,
            companyId: isset($metadata['company_id']) ? (string) $metadata['company_id'] : null,
            method: 'online_stripe',
        );
    }

    private function productName(AccountingDocument $document): string
    {
        return 'Facture '.$document->number;
    }
}
