<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Accounting\Application\DTOs\PaymentCheckout;
use App\Modules\Accounting\Application\DTOs\PaymentWebhookData;
use App\Modules\Accounting\Domain\Exceptions\PaymentGatewayNotConfiguredException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;
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

    /**
     * #7727 — identifiant du `tenant_payment_profile` (type stripe_keys) dont
     * proviennent les clés. Null = clés de la PLATEFORME (comportement
     * historique). Tracé dans les métadonnées du checkout.
     */
    private ?int $tenantProfileId = null;

    public function __construct(?PaymentGatewayConfigProviderInterface $gatewayConfig = null)
    {
        // #7726 : précédence BDD (admin plateforme, secrets chiffrés) →
        // fallback env — comportement historique inchangé sans ligne BDD.
        $gatewayConfig ??= app(PaymentGatewayConfigProviderInterface::class);
        $settings = $gatewayConfig->resolve('stripe');
        $this->secretKey = $settings['secret_key'] ?? '';
        $this->webhookSecret = $settings['webhook_secret'] ?? '';
    }

    /**
     * #7727 — variante de la passerelle opérant avec les clés Stripe PROPRES
     * du tenant (profil `stripe_keys` actif) : l'encaissement de la facture
     * client part sur le compte Stripe du tenant, pas celui de la plateforme.
     */
    public function withTenantCredentials(string $secretKey, string $webhookSecret, int $profileId): self
    {
        $clone = clone $this;
        $clone->secretKey = $secretKey;
        $clone->webhookSecret = $webhookSecret;
        $clone->tenantProfileId = $profileId;

        return $clone;
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
                // #7727 — traçabilité : quel profil de paiement tenant a
                // encaissé (absent = clés plateforme, comportement historique).
                ...($this->tenantProfileId !== null
                    ? ['metadata[payment_profile_id]' => (string) $this->tenantProfileId]
                    : []),
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

        // #7727 — un paiement encaissé avec les clés PROPRES d'un tenant est
        // notifié par le compte Stripe DU TENANT : sa signature n'est
        // vérifiable qu'avec le webhook secret du profil. Le company_id des
        // métadonnées (non fiable seul) ne sert qu'à SÉLECTIONNER le secret
        // candidat — la signature HMAC reste l'unique preuve (fail-closed).
        $tenantSecret = $this->tenantWebhookSecretFromPayload($payload);
        if ($tenantSecret !== null && $tenantSecret !== $this->webhookSecret) {
            return $this->verifyWithSecret($tenantSecret, $payload, $signatureHeader);
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

    /**
     * #7727 — webhook secret du profil `stripe_keys` actif du tenant désigné
     * par les métadonnées du payload (chemin non vérifié : sert uniquement à
     * choisir le secret candidat, jamais à accepter le payload).
     */
    private function tenantWebhookSecretFromPayload(string $payload): ?string
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return null;
        }

        $session = $data['data']['object'] ?? null;
        $metadata = is_array($session) && is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $companyId = isset($metadata['company_id']) ? (string) $metadata['company_id'] : '';

        if ($companyId === '') {
            return null;
        }

        return app(TenantPaymentProfileResolverInterface::class)
            ->stripeWebhookSecretForCompany($companyId);
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
