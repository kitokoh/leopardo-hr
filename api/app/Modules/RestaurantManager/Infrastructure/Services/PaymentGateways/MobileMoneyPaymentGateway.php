<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways;

use App\Modules\RestaurantManager\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Exceptions\PaymentGatewayException;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentResult;
use App\Modules\RestaurantManager\Domain\Payments\RefundRequest;
use App\Modules\RestaurantManager\Domain\Payments\RefundResult;
use App\Modules\RestaurantManager\Domain\Payments\VerifyPaymentRequest;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * RESTO-406 (#6193) — Adapter « mobile money ».
 *
 * Mode SANDBOX (défaut) : l'initiation retourne un paiement `pending` avec
 * une référence locale ; la confirmation arrive par le callback signé HMAC
 * (RESTO-407) — comportement historique inchangé.
 *
 * #7728 (BC-21/BC-25) — mode PRODUCTION feature-flaggé
 * (`restaurantmanager.mobile_money.production.enabled`) : l'initiation est
 * routée sur le profil `mobile_money` ACTIF du tenant (opérateur configuré +
 * numéro d'encaissement du restaurateur, contrat partagé #7727) vers
 * l'endpoint du provider (PVIT/Orange Money) configuré en env. La
 * confirmation reste portée par le callback HMAC existant. Fail-closed :
 * sans flag, sans profil actif ou sans endpoint →
 * `online_payment_not_configured` (422 message utilisateur, jamais un 500).
 * Aucun secret en dur : profils tenant chiffrés + config/env uniquement.
 */
final class MobileMoneyPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly TenantPaymentProfileResolverInterface $tenantProfiles,
    ) {}

    public function providerCode(): string
    {
        return 'mobile_money';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        if (config('restaurantmanager.mobile_money.sandbox', true)) {
            return new InitiatePaymentResult(
                status: PaymentStatus::PENDING,
                providerReference: 'MM-'.strtoupper((string) Str::uuid()),
                message: 'Paiement mobile money en attente de confirmation (callback signé).',
            );
        }

        return $this->initiateProduction($request);
    }

    public function verify(VerifyPaymentRequest $request): PaymentStatus
    {
        // Sandbox comme production : l'état réel est porté par le paiement,
        // écrit par le callback signé HMAC (RESTO-407).
        return PaymentStatus::PENDING;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return new RefundResult(
            status: PaymentStatus::PENDING,
            providerReference: 'MM-REFUND-'.strtoupper((string) Str::uuid()),
            message: 'Remboursement mobile money soumis (confirmation du provider).',
        );
    }

    /**
     * #7728 — première intégration production réelle : POST vers l'endpoint
     * du provider configuré, encaissement sur le numéro du PROFIL TENANT.
     */
    private function initiateProduction(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        if (! (bool) config('restaurantmanager.mobile_money.production.enabled', false)) {
            throw new PaymentGatewayException(
                'Mobile money payment is not configured for this restaurant.',
                'online_payment_not_configured',
            );
        }

        $profile = $this->tenantProfiles->activeMobileMoneyProfileForCompany($request->companyId);
        $initiateUrl = (string) config('restaurantmanager.mobile_money.production.initiate_url', '');

        if ($profile === null || $initiateUrl === '') {
            throw new PaymentGatewayException(
                'Mobile money payment is not configured for this restaurant.',
                'online_payment_not_configured',
            );
        }

        $apiKey = (string) config('restaurantmanager.mobile_money.production.api_key', '');

        $response = Http::withHeaders($apiKey !== '' ? ['Authorization' => 'Bearer '.$apiKey] : [])
            ->asJson()
            ->post($initiateUrl, [
                'merchant_id' => (string) config('restaurantmanager.mobile_money.merchant_id', ''),
                'operator' => $profile['operator'],
                'payee_msisdn' => $profile['phone_number'],
                'amount_minor' => $request->amountMinor,
                'currency' => $request->currency,
                'reference' => $request->reference,
                'idempotency_key' => $request->idempotencyKey,
            ]);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                'Mobile money payment could not be initiated.',
                'provider_unreachable',
                502,
            );
        }

        /** @var array{transaction_id?: string, checkout_url?: string} $payload */
        $payload = (array) $response->json();
        $reference = (string) ($payload['transaction_id'] ?? '');

        return new InitiatePaymentResult(
            status: PaymentStatus::PENDING,
            providerReference: $reference !== '' ? $reference : 'MM-'.strtoupper((string) Str::uuid()),
            message: 'Paiement mobile money initié — confirmation par le provider (callback signé).',
            checkoutUrl: isset($payload['checkout_url']) ? (string) $payload['checkout_url'] : null,
        );
    }
}
