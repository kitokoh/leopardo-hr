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
 * Adaptateur production PayDunya (FUND-101, spec §4.2) — mobile money
 * Afrique de l'Ouest (Orange Money, MTN, Moov, Wave…). Contrat
 * `FundraisingGatewayInterface` identique au sandbox : facture Checkout
 * (`POST /checkout-invoice/create`), IPN, et re-conciliation active
 * (`GET /checkout-invoice/confirm/{token}`).
 *
 * Sécurité :
 * - triple clé en config (`fundraising.mobile_money.paydunya.*`, env),
 *   jamais en dur ; non configuré ⇒ fail-closed ;
 * - IPN : le champ `data.hash` doit égaler `hash('sha512', master_key)`
 *   (convention PayDunya) — master key absente ou hash invalide ⇒ rejet
 *   (fail-closed #2614). L'en-tête de signature n'est pas utilisé par
 *   PayDunya : la preuve vit dans le payload vérifié.
 *
 * `gatewayName()` reste `mobile_money` (stabilité de l'annuaire
 * `fundraising_payment_routes`).
 */
final class PayDunyaGateway implements FundraisingGatewayInterface
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
        return $this->masterKey() !== '' && $this->privateKey() !== '' && $this->token() !== '';
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        if (! $this->isConfigured()) {
            throw FundraisingException::gatewayNotConfigured($this->gatewayName());
        }

        /** @var array<string, mixed> $actions */
        $actions = [
            'callback_url' => $this->notifyUrl(),
            'return_url' => $this->returnUrl(),
            'cancel_url' => $this->returnUrl(),
        ];
        $actions = array_filter($actions, static fn (mixed $value): bool => $value !== '' && $value !== null);

        $response = $this->http()
            ->post('/checkout-invoice/create', [
                'invoice' => [
                    'total_amount' => (int) round((float) $contribution->amount),
                    'description' => 'Contribution cagnotte — '.$contribution->reference,
                ],
                'actions' => $actions,
                'custom_data' => [
                    'contribution_reference' => $contribution->reference,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Fundraising PayDunya: initiation HTTP en échec', [
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.paydunya_initiation_failed');
        }

        $responseCode = $response->json('response_code');
        $invoiceToken = $response->json('token') ?? $response->json('data.invoice_token');
        $paymentUrl = $response->json('response_text');

        if ($responseCode !== '00' || ! is_string($invoiceToken) || $invoiceToken === '') {
            Log::error('Fundraising PayDunya: initiation refusée ou réponse malformée', [
                'response_code' => is_scalar($responseCode) ? (string) $responseCode : '?',
                'body' => (string) $response->body(),
            ]);

            throw new RuntimeException('fundraising.paydunya_initiation_failed');
        }

        return new GatewayPaymentInitiation(
            providerReference: $invoiceToken,
            redirectUrl: is_string($paymentUrl) && str_starts_with($paymentUrl, 'http') ? $paymentUrl : null,
            ussdCode: null,
            instructions: sprintf(
                'Confirmez le paiement de %s %s via la page sécurisée PayDunya (mobile money). Référence : %s.',
                number_format((float) $contribution->amount, 0, ',', ' '),
                strtoupper((string) $contribution->currency),
                $contribution->reference
            ),
            status: 'pending',
        );
    }

    /**
     * IPN PayDunya : pas d'en-tête de signature — la preuve est le champ
     * `data.hash` qui doit égaler SHA-512 de la master key.
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        $masterKey = $this->masterKey();

        if ($masterKey === '') {
            Log::error('Fundraising PayDunya: master key absente — IPN REJETÉE (fail-closed).');

            return null;
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return null;
        }

        $hash = $data['data']['hash'] ?? null;

        if (! is_string($hash) || ! hash_equals(hash('sha512', $masterKey), $hash)) {
            Log::warning('Fundraising PayDunya: IPN hash mismatch');

            return null;
        }

        return $data;
    }

    /**
     * Payload IPN PayDunya : `data.invoice_token` (= provider_reference
     * posé à l'initiation), `data.status` dans {completed, failed,
     * cancelled}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        $inner = $payload['data'] ?? [];

        if (! is_array($inner)) {
            return null;
        }

        $token = (string) ($inner['invoice_token'] ?? '');
        $status = strtolower((string) ($inner['status'] ?? ''));

        if ($token === '' || ! in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: $token.'-'.$status,
            providerReference: $token,
            paid: $status === 'completed',
            paidAt: $status === 'completed' ? now()->toIso8601String() : null,
        );
    }

    /**
     * Re-conciliation active PayDunya : `GET /checkout-invoice/confirm/{token}`.
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = $this->http()->get('/checkout-invoice/confirm/'.rawurlencode($providerReference));

        if (! $response->successful()) {
            return null;
        }

        $status = strtolower((string) ($response->json('data.status') ?? ''));

        if (! in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: 'reconcile-'.$providerReference,
            providerReference: $providerReference,
            paid: $status === 'completed',
            paidAt: $status === 'completed' ? now()->toIso8601String() : null,
        );
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUri())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withHeaders([
                'PAYDUNYA-MASTER-KEY' => $this->masterKey(),
                'PAYDUNYA-PRIVATE-KEY' => $this->privateKey(),
                'PAYDUNYA-TOKEN' => $this->token(),
            ]);
    }

    private function baseUri(): string
    {
        $baseUri = rtrim((string) ($this->config['base_uri'] ?? ''), '/');

        return $baseUri !== '' ? $baseUri : 'https://app.paydunya.com/api/v1';
    }

    private function masterKey(): string
    {
        return (string) ($this->config['master_key'] ?? '');
    }

    private function privateKey(): string
    {
        return (string) ($this->config['private_key'] ?? '');
    }

    private function token(): string
    {
        return (string) ($this->config['token'] ?? '');
    }

    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);

        return $timeout > 0 ? $timeout : 15;
    }

    private function notifyUrl(): string
    {
        $baseUrl = rtrim((string) ($this->config['api_base_url'] ?? ''), '/');

        return $baseUrl !== '' ? $baseUrl.'/api/v1/webhooks/fundraising/paydunya' : '';
    }

    private function returnUrl(): string
    {
        return rtrim((string) ($this->config['public_base_url'] ?? ''), '/');
    }
}
