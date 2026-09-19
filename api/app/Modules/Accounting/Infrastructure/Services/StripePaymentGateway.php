<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Accounting\Application\DTOs\PaymentCheckout;
use App\Modules\Accounting\Application\DTOs\PaymentWebhookData;
use App\Modules\Accounting\Domain\Exceptions\PaymentGatewayNotConfiguredException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
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

    private string $webhookSecret;

    public function __construct(?PaymentGatewayConfigProviderInterface $gatewayConfig = null)
    {
        // #7726 : précédence BDD (admin plateforme, secrets chiffrés) →
        // fallback env — comportement historique inchangé sans ligne BDD.
        $gatewayConfig ??= app(PaymentGatewayConfigProviderInterface::class);
        $settings = $gatewayConfig->resolve('stripe');
        $this->secretKey = $settings['secret_key'] ?? '';
        $this->webhookSecret = $settings['webhook_secret'] ?? '';
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
        // #7726 — le secret provient de la config résolue (BDD → env), ou des
        // clés du tenant si la passerelle a été clonée via withTenantCredentials.
        $data = $this->verifyWithSecret($this->webhookSecret, $payload, $signatureHeader);
        if ($data !== null) {
            return $data;
        }

        return null;
    }

    /**
     * Vérification HMAC Stripe (`t=<ts>,v1=<sig>`) avec un secret donné.
     *
     * @return array<string, mixed>|null
     */
    private function verifyWithSecret(string $secret, string $payload, string $signatureHeader): ?array
    {
        if ($secret === '') {
            // #2614 fail-closed : secret absent = webhook non vérifiable = rejet.
            Log::error('Stripe: webhook secret not configured — webhook REJETÉ (fail-closed).');

            return null;
        }

        $elements = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                // Segment sans « clé=valeur » (ex. timestamp nu) : ignoré — le
                // format Stripe est `t=<ts>,v1=<sig>`, chaque segment porte « = ».
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
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

    private function productName(AccountingDocument $document): string
    {
        return 'Facture '.$document->number;
    }
}
