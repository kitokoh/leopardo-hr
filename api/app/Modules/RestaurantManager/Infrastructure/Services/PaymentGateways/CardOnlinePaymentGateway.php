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

/**
 * #7728 (BC-25 RESTAURANT / BC-21) — Adapter « carte en ligne » : Stripe
 * Checkout Session sur les CLÉS PROPRES du tenant (profil `stripe_keys`
 * actif, contrat partagé #7727). L'encaissement d'une commande publique part
 * sur le compte Stripe DU RESTAURATEUR, jamais celui de la plateforme.
 *
 * Fail-closed : sans profil actif, `initiate` lève
 * `online_payment_not_configured` (converti en 422 message utilisateur par
 * l'appelant — jamais un 500). REST sans SDK (pattern StripePaymentGateway
 * Accounting) ; montants déjà en minor units côté restaurant.
 */
final class CardOnlinePaymentGateway implements PaymentGatewayInterface
{
    public const PROVIDER_CODE = 'card_online';

    private const API_URL = 'https://api.stripe.com';

    public function __construct(
        private readonly TenantPaymentProfileResolverInterface $tenantProfiles,
    ) {}

    public function providerCode(): string
    {
        return self::PROVIDER_CODE;
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        $credentials = $this->credentialsOrFail($request->companyId);

        $response = Http::withToken($credentials['secret_key'], 'Bearer')
            ->asForm()
            ->post(self::API_URL.'/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($request->currency),
                'line_items[0][price_data][unit_amount]' => $request->amountMinor,
                'line_items[0][price_data][product_data][name]' => 'Commande '.$request->reference,
                'client_reference_id' => $request->reference,
                'success_url' => $this->returnUrl('success'),
                'cancel_url' => $this->returnUrl('cancel'),
                'metadata[company_id]' => $request->companyId,
                'metadata[restaurant_order_reference]' => $request->reference,
                'metadata[tenant_payment_profile_id]' => (string) $credentials['profile_id'],
                'metadata[idempotency_key]' => $request->idempotencyKey,
            ]);

        if ($response->failed()) {
            // Aucun détail PSP ne fuite vers le client (code stable, message sûr).
            throw new PaymentGatewayException(
                'Card online payment could not be initiated.',
                'provider_unreachable',
                502,
            );
        }

        /** @var array{id?: string, url?: string} $payload */
        $payload = (array) $response->json();

        return new InitiatePaymentResult(
            status: PaymentStatus::PENDING,
            providerReference: (string) ($payload['id'] ?? ''),
            message: 'Paiement par carte en ligne initié — redirection vers la page de paiement.',
            checkoutUrl: isset($payload['url']) ? (string) $payload['url'] : null,
        );
    }

    public function verify(VerifyPaymentRequest $request): PaymentStatus
    {
        $credentials = $this->credentialsOrFail($request->companyId);

        $response = Http::withToken($credentials['secret_key'], 'Bearer')
            ->get(self::API_URL.'/v1/checkout/sessions/'.$request->providerReference);

        if ($response->failed()) {
            return PaymentStatus::PENDING;
        }

        /** @var array{payment_status?: string, status?: string} $payload */
        $payload = (array) $response->json();

        if (($payload['payment_status'] ?? '') === 'paid') {
            return PaymentStatus::CONFIRMED;
        }

        return ($payload['status'] ?? '') === 'expired'
            ? PaymentStatus::FAILED
            : PaymentStatus::PENDING;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $credentials = $this->credentialsOrFail($request->companyId);

        $session = Http::withToken($credentials['secret_key'], 'Bearer')
            ->get(self::API_URL.'/v1/checkout/sessions/'.$request->providerReference);

        /** @var array{payment_intent?: string|null} $sessionPayload */
        $sessionPayload = (array) $session->json();
        $paymentIntent = (string) ($sessionPayload['payment_intent'] ?? '');

        if ($session->failed() || $paymentIntent === '') {
            throw new PaymentGatewayException(
                'Card online refund could not be initiated.',
                'provider_unreachable',
                502,
            );
        }

        $response = Http::withToken($credentials['secret_key'], 'Bearer')
            ->asForm()
            ->post(self::API_URL.'/v1/refunds', [
                'payment_intent' => $paymentIntent,
                'amount' => $request->amountMinor,
                'metadata[reason_code]' => $request->reasonCode,
            ]);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                'Card online refund could not be initiated.',
                'provider_unreachable',
                502,
            );
        }

        /** @var array{id?: string, status?: string} $payload */
        $payload = (array) $response->json();

        return new RefundResult(
            status: ($payload['status'] ?? '') === 'succeeded' ? PaymentStatus::REFUNDED : PaymentStatus::PENDING,
            providerReference: (string) ($payload['id'] ?? ''),
            message: 'Remboursement carte en ligne soumis au PSP du restaurateur.',
        );
    }

    /**
     * Clés Stripe PROPRES du tenant — fail-closed sans profil actif : aucun
     * fallback plateforme sur la surface publique restaurant.
     *
     * @return array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}
     */
    private function credentialsOrFail(string $companyId): array
    {
        $credentials = $this->tenantProfiles->stripeCredentialsForCompany($companyId);

        if ($credentials === null || $credentials['secret_key'] === '') {
            throw new PaymentGatewayException(
                'Online card payment is not configured for this restaurant.',
                'online_payment_not_configured',
            );
        }

        return $credentials;
    }

    private function returnUrl(string $outcome): string
    {
        $configured = config(sprintf('restaurantmanager.card_online.%s_url', $outcome));

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/order?payment='.$outcome;
    }
}
