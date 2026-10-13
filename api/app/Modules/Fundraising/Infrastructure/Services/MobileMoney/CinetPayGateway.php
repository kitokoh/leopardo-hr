<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services\MobileMoney;

use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Adaptateur production CinetPay (FUND-101, spec §4.2) — mobile money
 * Afrique de l'Ouest et Centrale (Orange Money, MTN MoMo, Moov, Wave
 * selon pays). Contrat `FundraisingGatewayInterface` identique au sandbox
 * `MobileMoneyGateway` : initiation Checkout (`POST /payment`), webhook
 * IPN, et re-conciliation active (`POST /payment/check`).
 *
 * Sécurité :
 * - identifiants en config (`fundraising.mobile_money.cinetpay.*`, env),
 *   jamais en dur ; non configuré ⇒ fail-closed
 *   (`PAYMENT_GATEWAY_NOT_CONFIGURED`) ;
 * - webhook : HMAC-SHA256 du corps brut avec `webhook_secret` (en-tête
 *   `X-Signature`, convention maison alignée sur l'agrégateur générique) —
 *   secret absent ou signature invalide ⇒ rejet (fail-closed #2614).
 *
 * `gatewayName()` reste `mobile_money` : l'annuaire
 * `fundraising_payment_routes` et le routage webhook sont stables quel que
 * soit le provider configuré.
 */
final class CinetPayGateway implements FundraisingGatewayInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function gatewayName(): string
    {
        return 'mobile_money';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->siteId() !== '';
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        if (! $this->isConfigured()) {
            throw FundraisingException::gatewayNotConfigured($this->gatewayName());
        }

        /** @var array<string, mixed> $body */
        $body = [
            'apikey' => $this->apiKey(),
            'site_id' => $this->siteId(),
            'transaction_id' => $contribution->reference,
            'amount' => (int) round((float) $contribution->amount),
            'currency' => strtoupper((string) $contribution->currency),
            'description' => 'Contribution cagnotte — '.$contribution->reference,
            'customer_phone_number' => (string) ($contribution->contributor_phone ?? ''),
            'notify_url' => $this->notifyUrl(),
            'return_url' => $this->returnUrl(),
            'channels' => 'MOBILE_MONEY',
        ];
        $body = array_filter($body, static fn (mixed $value): bool => $value !== '' && $value !== null);

        $response = Http::baseUrl($this->baseUri())
            ->timeout($this->timeout())
            ->acceptJson()
            ->post('/payment', $body);

        if (! $response->successful()) {
            Log::error('Fundraising CinetPay: initiation HTTP en échec', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.cinetpay_initiation_failed');
        }

        $code = $response->json('code');
        $paymentUrl = $response->json('data.payment_url');

        if ($code !== '201' || ! is_string($paymentUrl) || $paymentUrl === '') {
            Log::error('Fundraising CinetPay: initiation refusée ou réponse malformée', [
                'code' => is_scalar($code) ? (string) $code : '?',
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.cinetpay_initiation_failed');
        }

        return new GatewayPaymentInitiation(
            providerReference: (string) $contribution->reference,
            redirectUrl: $paymentUrl,
            ussdCode: null,
            instructions: sprintf(
                'Confirmez le paiement de %s %s via la page sécurisée CinetPay (mobile money). Référence : %s.',
                number_format((float) $contribution->amount, 0, ',', ' '),
                strtoupper((string) $contribution->currency),
                $contribution->reference
            ),
            status: 'pending',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        if ($secret === '') {
            Log::error('Fundraising CinetPay: webhook secret absent — webhook REJETÉ (fail-closed).');

            return null;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, trim($signatureHeader))) {
            Log::warning('Fundraising CinetPay: webhook signature mismatch');

            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Payload IPN CinetPay : `cpm_trans_id` (= référence posée à
     * l'initiation), `status`/`payment_status` dans {ACCEPTED, REFUSED,
     * CANCELLED}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        $eventId = (string) ($payload['cpm_trans_id'] ?? $payload['transaction_id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? $payload['payment_status'] ?? ''));

        if ($eventId === '' || ! in_array($status, ['ACCEPTED', 'REFUSED', 'CANCELLED'], true)) {
            return null;
        }

        $paidAt = $payload['payment_date'] ?? null;

        return new GatewayPaymentUpdate(
            eventId: $eventId,
            providerReference: $eventId,
            paid: $status === 'ACCEPTED',
            paidAt: $status === 'ACCEPTED'
                ? (is_string($paidAt) && $paidAt !== '' ? $paidAt : now()->toIso8601String())
                : null,
        );
    }

    /**
     * Re-conciliation active CinetPay : `POST /payment/check` — statut
     * `ACCEPTED` ⇒ payé, `REFUSED`/`CANCELLED` ⇒ échoué, sinon null
     * (provider injoignable ou transaction encore en attente).
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::baseUrl($this->baseUri())
            ->timeout($this->timeout())
            ->acceptJson()
            ->post('/payment/check', [
                'apikey' => $this->apiKey(),
                'site_id' => $this->siteId(),
                'transaction_id' => $providerReference,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $status = strtoupper((string) ($response->json('data.status') ?? ''));

        if (! in_array($status, ['ACCEPTED', 'REFUSED', 'CANCELLED'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: 'reconcile-'.$providerReference,
            providerReference: $providerReference,
            paid: $status === 'ACCEPTED',
            paidAt: $status === 'ACCEPTED' ? now()->toIso8601String() : null,
        );
    }

    private function baseUri(): string
    {
        $baseUri = rtrim((string) ($this->config['base_uri'] ?? ''), '/');

        return $baseUri !== '' ? $baseUri : 'https://api-checkout.cinetpay.com/v2';
    }

    private function apiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }

    private function siteId(): string
    {
        return (string) ($this->config['site_id'] ?? '');
    }

    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);

        return $timeout > 0 ? $timeout : 15;
    }

    private function notifyUrl(): string
    {
        $baseUrl = rtrim((string) ($this->config['api_base_url'] ?? ''), '/');

        return $baseUrl !== '' ? $baseUrl.'/api/v1/webhooks/fundraising/cinetpay' : '';
    }

    private function returnUrl(): string
    {
        return rtrim((string) ($this->config['public_base_url'] ?? ''), '/');
    }
}
