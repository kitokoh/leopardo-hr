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
 * Adaptateur production PVIT (FUND-101, spec §4.2) — agrégateur mobile
 * money config-driven (pattern TRAVEL-407 porté en HTTP réel). Contrat
 * `FundraisingGatewayInterface` identique au sandbox : initiation
 * (`POST {base_uri}/payments`), webhook agrégateur, re-conciliation
 * active (`GET {base_uri}/payments/{reference}`).
 *
 * Les endpoints exacts dépendent du déploiement de l'agrégateur : ils
 * suivent la convention REST ci-dessus, ajustable par `base_uri` —
 * aucune clé ni URL de secours en dur : sans `base_uri` ni
 * `merchant_tel`/`api_key`, l'adaptateur est fail-closed.
 *
 * Webhook : HMAC-SHA256 du corps brut avec `webhook_secret` (en-tête
 * `X-Signature`, convention maison) — secret absent ou signature
 * invalide ⇒ rejet (fail-closed #2614).
 *
 * `gatewayName()` reste `mobile_money` (stabilité de l'annuaire
 * `fundraising_payment_routes`).
 */
final class PvitGateway implements FundraisingGatewayInterface
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
        return $this->baseUri() !== ''
            && (string) ($this->config['merchant_tel'] ?? '') !== ''
            && (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        if (! $this->isConfigured()) {
            throw FundraisingException::gatewayNotConfigured($this->gatewayName());
        }

        /** @var array<string, mixed> $body */
        $body = [
            'merchant_tel' => (string) $this->config['merchant_tel'],
            'reference' => $contribution->reference,
            'amount' => (int) round((float) $contribution->amount),
            'currency' => strtoupper((string) $contribution->currency),
            'customer_phone' => (string) ($contribution->contributor_phone ?? ''),
            'notify_url' => $this->notifyUrl(),
        ];
        $body = array_filter($body, static fn (mixed $value): bool => $value !== '' && $value !== null);

        $response = Http::baseUrl($this->baseUri())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withToken((string) $this->config['api_key'])
            ->post('/payments', $body);

        if (! $response->successful()) {
            Log::error('Fundraising PVIT: initiation HTTP en échec', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.pvit_initiation_failed');
        }

        $providerReference = $response->json('reference') ?? $response->json('transaction_id');

        if (! is_string($providerReference) || $providerReference === '') {
            Log::error('Fundraising PVIT: réponse d\'initiation malformée', ['body' => (string) $response->body()]);

            throw new RuntimeException('fundraising.pvit_initiation_failed');
        }

        $ussdCode = $response->json('ussd_code');
        $redirectUrl = $response->json('payment_url');
        $instructions = $response->json('instructions');

        return new GatewayPaymentInitiation(
            providerReference: $providerReference,
            redirectUrl: is_string($redirectUrl) && str_starts_with($redirectUrl, 'http') ? $redirectUrl : null,
            ussdCode: is_string($ussdCode) && $ussdCode !== '' ? $ussdCode : null,
            instructions: is_string($instructions) && $instructions !== ''
                ? $instructions
                : sprintf(
                    'Confirmez le paiement de %s %s sur votre mobile. Référence : %s.',
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
            Log::error('Fundraising PVIT: webhook secret absent — webhook REJETÉ (fail-closed).');

            return null;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, trim($signatureHeader))) {
            Log::warning('Fundraising PVIT: webhook signature mismatch');

            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Payload agrégateur PVIT : `{transaction_id, reference, status,
     * paid_at?}` — `status` dans {paid, failed}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        $eventId = (string) ($payload['transaction_id'] ?? '');
        $reference = (string) ($payload['reference'] ?? '');
        $status = strtolower((string) ($payload['status'] ?? ''));

        if ($eventId === '' || $reference === '' || ! in_array($status, ['paid', 'failed'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: $eventId,
            providerReference: $reference,
            paid: $status === 'paid',
            paidAt: $status === 'paid'
                ? (isset($payload['paid_at']) && is_string($payload['paid_at']) ? $payload['paid_at'] : now()->toIso8601String())
                : null,
        );
    }

    /**
     * Re-conciliation active PVIT : `GET /payments/{reference}`.
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::baseUrl($this->baseUri())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withToken((string) $this->config['api_key'])
            ->get('/payments/'.rawurlencode($providerReference));

        if (! $response->successful()) {
            return null;
        }

        $status = strtolower((string) ($response->json('status') ?? ''));

        if (! in_array($status, ['paid', 'failed'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: 'reconcile-'.$providerReference,
            providerReference: $providerReference,
            paid: $status === 'paid',
            paidAt: $status === 'paid' ? now()->toIso8601String() : null,
        );
    }

    private function baseUri(): string
    {
        return rtrim((string) ($this->config['base_uri'] ?? ''), '/');
    }

    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);

        return $timeout > 0 ? $timeout : 15;
    }

    private function notifyUrl(): string
    {
        $baseUrl = rtrim((string) ($this->config['api_base_url'] ?? ''), '/');

        return $baseUrl !== '' ? $baseUrl.'/api/v1/webhooks/fundraising/pvit' : '';
    }
}
