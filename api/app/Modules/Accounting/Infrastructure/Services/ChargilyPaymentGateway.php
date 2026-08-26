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
 * #5272 — Passerelle Chargily (Algérie) pour le paiement en ligne des
 * documents comptables (ADR-0017 option A, pilote DZ).
 *
 * API v2 (dev.chargily.com) : POST /api/v2/checkouts, auth Bearer api_key,
 * montant en unité mineure (centimes DZD). Webhook : en-tête
 * X-Chargily-Signature `sha256=<hmac>`, HMAC-SHA256 du corps brut.
 */
final class ChargilyPaymentGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://pay.chargily.net';

    /** Tolérance anti-fraude sur le montant notifié (unités mineures). */
    public const AMOUNT_TOLERANCE_MINOR = 2;

    private string $apiKey;

    private string $mode;

    public function __construct()
    {
        $this->apiKey = (string) config('services.chargily.api_key');
        $this->mode = (string) config('services.chargily.mode', 'live');
    }

    public function gatewayName(): string
    {
        return 'chargily';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function createCheckout(
        AccountingDocument $document,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): PaymentCheckout {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayNotConfiguredException('DZ');
        }

        $response = Http::withToken($this->apiKey, 'Bearer')
            ->acceptJson()
            ->post($this->endpoint('checkouts'), [
                'amount' => GatewayMoney::toMinorUnits($amount, 'DZD'),
                'currency' => 'dzd',
                'success_url' => $successUrl,
                'failure_url' => $cancelUrl,
                'webhook_url' => $this->webhookUrl(),
                // Métadonnées de rapprochement : le webhook ne reçoit que le
                // payload signé — document_id/company_id permettent de résoudre
                // le tenant et le document sans état côté serveur.
                'metadata' => [
                    'document_id' => $document->id,
                    'company_id' => (string) $document->company_id,
                    'document_number' => $document->number,
                ],
                'locale' => 'fr',
            ]);

        if (! $response->successful()) {
            Log::error('Chargily: failed to create checkout', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException(__('accounting.errors.gateway_checkout_failed'));
        }

        $id = $response->json('id');
        $url = $response->json('checkout_url');

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            Log::error('Chargily: malformed checkout response', ['body' => (string) $response->body()]);

            throw new RuntimeException(__('accounting.errors.gateway_checkout_failed'));
        }

        return new PaymentCheckout(
            url: $url,
            gatewayCheckoutId: $id,
            gateway: $this->gatewayName(),
            expiresAt: CarbonImmutable::now()->addHours(24),
        );
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) config('services.chargily.webhook_secret');

        if ($secret === '') {
            // #2615 fail-closed : secret absent = webhook non vérifiable = rejet.
            Log::error('Chargily: webhook secret not configured — webhook REJETÉ (fail-closed).');

            return null;
        }

        $provided = $signatureHeader;
        if (str_starts_with($signatureHeader, 'sha256=')) {
            $provided = substr($signatureHeader, 7);
        }

        if ($provided === '') {
            Log::warning('Chargily: missing or malformed signature header.');

            return null;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $provided)) {
            Log::warning('Chargily: webhook signature mismatch.');

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
        $checkout = $payload['data'] ?? [];

        if (! is_array($checkout)) {
            return null;
        }

        $id = (string) ($checkout['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $eventType = match ($type) {
            'checkout.paid' => 'paid',
            'checkout.canceled' => 'cancelled',
            default => 'other',
        };

        $metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];

        return new PaymentWebhookData(
            gatewayPaymentId: $id,
            amountMinor: (int) ($checkout['amount'] ?? 0),
            currency: strtoupper((string) ($checkout['currency'] ?? 'dzd')),
            eventType: $eventType,
            documentId: isset($metadata['document_id']) ? (int) $metadata['document_id'] : null,
            companyId: isset($metadata['company_id']) ? (string) $metadata['company_id'] : null,
            method: 'online_chargily',
        );
    }

    private function endpoint(string $resource): string
    {
        $base = $this->mode === 'test'
            ? self::BASE_URL.'/test'
            : self::BASE_URL;

        return $base.'/api/v2/'.$resource;
    }

    private function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/accounting/payment-webhooks/chargily';
    }
}
