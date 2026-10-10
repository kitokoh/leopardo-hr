<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Application\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Application\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle mobile money des contributions de cagnottes — méthode
 * `mobile_money` (verticale FUNDRAISING, spec §4.2).
 *
 * Pattern TRAVEL-407 (`PvitPaymentGateway`) : agrégateur **config-driven**
 * (`fundraising.mobile_money.*`), identifiants en env, jamais en dur.
 * Opérateurs cibles selon pays : Orange Money, MTN MoMo, Wave, Moov Money
 * (la sélection par pays tenant est branchée côté config, phase v1.2).
 *
 * **Mode sandbox** (défaut, `sandbox: true`) : `initiate()` simule un push
 * USSD accepté avec référence `MM-*` ; `verify()` confirme le paiement
 * (re-conciliation active, événement déterministe idempotent). Le contrat
 * reste identique pour l'adaptateur production (CinetPay / PayDunya /
 * PVIT — TODO FUND-101, aucune clé en dur).
 *
 * Webhook agrégateur : HMAC-SHA256 du corps avec
 * `fundraising.mobile_money.webhook_secret` — secret absent ⇒ rejet
 * (fail-closed #2614).
 */
final class MobileMoneyGateway implements FundraisingGatewayInterface
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
        // En sandbox, l'agrégateur est toujours « configuré » (pas de clé
        // requise) — même décision que PVIT. En production, la clé API de
        // l'agrégateur devient obligatoire (fail-closed).
        if ((bool) ($this->config['sandbox'] ?? true)) {
            return true;
        }

        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        $operator = (string) ($this->config['operator'] ?? 'sandbox');

        if (! $this->isConfigured()) {
            throw \App\Modules\Fundraising\Domain\Exceptions\FundraisingException::gatewayNotConfigured($this->gatewayName());
        }

        // Sandbox : simulation d'un push USSD/mobile money accepté.
        $reference = 'MM-'.strtoupper(bin2hex(random_bytes(8)));

        return new GatewayPaymentInitiation(
            providerReference: $reference,
            redirectUrl: null,
            ussdCode: sprintf('#144*82*%s#', $reference),
            instructions: sprintf(
                'Confirmez le paiement de %s %s sur votre mobile (%s). Référence : %s.',
                number_format((float) $contribution->amount, 0, ',', ' '),
                (string) $contribution->currency,
                $operator,
                $contribution->reference
            ),
            status: 'pending',
        );
    }

    /**
     * Webhook agrégateur production : HMAC-SHA256 du corps brut, en-tête
     * `X-Signature`. Sandbox sans secret ⇒ null (rejet fail-closed — le
     * sandbox confirme via `verify()`, jamais via webhook).
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        if ($secret === '') {
            Log::error('Fundraising MobileMoney: webhook secret absent — webhook REJETÉ (fail-closed).');

            return null;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, trim($signatureHeader))) {
            Log::warning('Fundraising MobileMoney: webhook signature mismatch');

            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Payload agrégateur générique : `{transaction_id, reference, status,
     * paid_at?}` — `status` dans {paid, failed}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        $eventId = (string) ($payload['transaction_id'] ?? '');
        $reference = (string) ($payload['reference'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($eventId === '' || $reference === '' || ! in_array($status, ['paid', 'failed'], true)) {
            return null;
        }

        return new GatewayPaymentUpdate(
            eventId: $eventId,
            providerReference: $reference,
            paid: $status === 'paid',
            paidAt: isset($payload['paid_at']) && is_string($payload['paid_at'])
                ? $payload['paid_at']
                : ($status === 'paid' ? now()->toIso8601String() : null),
        );
    }

    /**
     * Vérification active (re-conciliation) : en sandbox, tout paiement
     * initié est considéré confirmé — événement DÉTERMINISTE par référence
     * (double appel sans double crédit, idempotence spec §4.3).
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        if ((bool) ($this->config['sandbox'] ?? true)) {
            return new GatewayPaymentUpdate(
                eventId: 'sandbox-'.$providerReference,
                providerReference: $providerReference,
                paid: true,
                paidAt: now()->toIso8601String(),
            );
        }

        // TODO FUND-101 : GET statut transaction agrégateur (CinetPay /
        // PayDunya / PVIT) — même contrat, clé API en config.
        return null;
    }
}
