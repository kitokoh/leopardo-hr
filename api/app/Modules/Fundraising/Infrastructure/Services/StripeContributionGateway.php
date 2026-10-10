<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Passerelle Stripe (Checkout Session, paiement unique) des contributions
 * de cagnottes — méthode `card` (verticale FUNDRAISING, spec §4.2).
 *
 * REST sans SDK (pattern `StripePaymentGateway` Accounting, ADR-0017) :
 * POST /v1/checkout/sessions, montant en unité mineure (`FundraisingMoney`,
 * devises 0 décimale XOF/XAF gérées). Webhook : en-tête Stripe-Signature
 * `t=..,v1=..`, HMAC-SHA256 de `t.payload`, rejet des événements > 5 min
 * (anti-rejeu), secret absent ⇒ rejet (fail-closed #2614).
 *
 * Clés en config (`fundraising.stripe.*`, env), jamais en dur.
 * `isConfigured() === false` ⇒ initiation refusée (503
 * PAYMENT_GATEWAY_NOT_CONFIGURED) — jamais de fallback silencieux.
 */
final class StripeContributionGateway implements FundraisingGatewayInterface
{
    private const API_URL = 'https://api.stripe.com';

    private readonly string $secretKey;

    private readonly string $webhookSecret;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->webhookSecret = (string) ($config['webhook_secret'] ?? '');
    }

    public function gatewayName(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        if (! $this->isConfigured()) {
            throw FundraisingException::gatewayNotConfigured($this->gatewayName());
        }

        $fundraiser = $contribution->fundraiser;
        $currency = strtolower((string) $contribution->currency);
        $baseUrl = rtrim((string) ($this->config['public_base_url'] ?? ''), '/');

        $response = Http::withToken($this->secretKey, 'Bearer')
            ->asForm()
            ->post(self::API_URL.'/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => FundraisingMoney::toMinorUnits(
                    (float) $contribution->amount,
                    (string) $contribution->currency
                ),
                'line_items[0][price_data][product_data][name]' => 'Contribution — '.($fundraiser->title ?? 'cagnotte'),
                'success_url' => $baseUrl.'/cagnottes/'.($fundraiser->slug ?? '').'/merci?reference='.$contribution->reference,
                'cancel_url' => $baseUrl.'/cagnottes/'.($fundraiser->slug ?? '').'?annule=1',
                'metadata[contribution_reference]' => $contribution->reference,
                'metadata[company_id]' => (string) $contribution->company_id,
                // Propagé au PaymentIntent : permet de clore proprement un
                // paiement échoué (`payment_intent.payment_failed`) en
                // retrouvant la contribution par sa référence publique.
                'payment_intent_data[metadata][contribution_reference]' => $contribution->reference,
            ]);

        if (! $response->successful()) {
            Log::error('Fundraising Stripe: failed to create checkout session', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.gateway_checkout_failed');
        }

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            Log::error('Fundraising Stripe: malformed checkout session response', ['body' => (string) $response->body()]);

            throw new RuntimeException('fundraising.gateway_checkout_failed');
        }

        return new GatewayPaymentInitiation(
            providerReference: $id,
            redirectUrl: $url,
            ussdCode: null,
            instructions: null,
            status: 'pending',
        );
    }

    /**
     * Vérification HMAC Stripe (`t=<ts>,v1=<sig>`) — fail-closed (#2614) :
     * secret absent, format invalide, événement trop vieux ou signature
     * différente ⇒ null (webhook rejeté).
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        if ($this->webhookSecret === '') {
            Log::error('Fundraising Stripe: webhook secret absent — webhook REJETÉ (fail-closed).');

            return null;
        }

        $elements = [];
        $v1Signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            if ($key === 'v1') {
                // Rotation de secret Stripe : PLUSIEURS v1 peuvent être
                // présents — une signature valide suffit (jamais d'écrasement).
                $v1Signatures[] = $value;
                continue;
            }
            $elements[$key] = $value;
        }

        $timestamp = (string) ($elements['t'] ?? '');

        if ($timestamp === '' || $v1Signatures === []) {
            return null;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Fundraising Stripe: webhook timestamp too old', ['timestamp' => $timestamp]);

            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        $signatureValid = false;
        foreach ($v1Signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $signatureValid = true;
                break;
            }
        }

        if (! $signatureValid) {
            Log::warning('Fundraising Stripe: webhook signature mismatch');

            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Événements exploitables : `checkout.session.completed` (payé),
     * `checkout.session.expired` (échoué). La session Stripe est
     * `provider_reference` de la contribution (posé à l'initiation).
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        $type = (string) ($payload['type'] ?? '');
        $eventId = (string) ($payload['id'] ?? '');

        if ($eventId === '') {
            return null;
        }

        /** @var array<string, mixed> $object */
        $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
        $sessionId = (string) ($object['id'] ?? '');

        if ($sessionId === '') {
            return null;
        }

        return match ($type) {
            'checkout.session.completed' => new GatewayPaymentUpdate(
                eventId: $eventId,
                providerReference: $sessionId,
                paid: true,
                paidAt: isset($object['created']) && is_int($object['created'])
                    ? date('c', $object['created'])
                    : now()->toIso8601String(),
            ),
            'checkout.session.expired' => new GatewayPaymentUpdate(
                eventId: $eventId,
                providerReference: $sessionId,
                paid: false,
                paidAt: null,
            ),
            // Carte refusée : le PaymentIntent porte la référence publique
            // (posée à l'initiation) — `ApplyPaymentUpdate` retombe sur la
            // recherche par `reference` quand `provider_reference` ne match
            // pas (la session Stripe reste l'identifiant canonique).
            'payment_intent.payment_failed' => ($metadataReference = (string) ($object['metadata']['contribution_reference'] ?? '')) !== ''
                ? new GatewayPaymentUpdate(
                    eventId: $eventId,
                    providerReference: $metadataReference,
                    paid: false,
                    paidAt: null,
                )
                : null,
            default => null,
        };
    }

    /**
     * Vérification active : état de la Checkout Session (re-conciliation
     * si le webhook tarde — même rôle que `PvitPaymentGateway::verify`).
     *
     * NE TRANCHE QUE SUR ÉTAT TERMINAL : `complete`+`paid` ⇒ payé,
     * `expired` ⇒ échoué, TOUT LE RESTE ⇒ null (aucune mise à jour). Une
     * session encore `open` (client sur la page Checkout) ne doit JAMAIS
     * être soldée en échec — sinon le webhook de succès ultérieur serait
     * refusé (argent encaissé, jamais crédité — leçon revue statique).
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::withToken($this->secretKey, 'Bearer')
            ->get(self::API_URL.'/v1/checkout/sessions/'.$providerReference);

        if (! $response->successful()) {
            return null;
        }

        $status = (string) $response->json('status', '');
        $paymentStatus = (string) $response->json('payment_status', '');

        if ($status === 'open') {
            // Paiement potentiellement en cours — pas de verdict.
            return null;
        }

        $paid = $status === 'complete' && $paymentStatus === 'paid';

        if (! $paid && $status !== 'expired') {
            // État intermédiaire inconnu : pas de verdict non plus.
            return null;
        }

        return new GatewayPaymentUpdate(
            // Événement synthétique DÉTERMINISTE : la re-vérification d'une
            // même session ne produit jamais un second crédit (idempotence
            // fundraising_payment_events + garde statut contribution).
            eventId: 'verify-'.$providerReference,
            providerReference: $providerReference,
            paid: $paid,
            paidAt: $paid ? now()->toIso8601String() : null,
        );
    }
}
