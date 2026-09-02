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
use Illuminate\Support\Str;

/**
 * RESTO-406 (#6193) — Adapter « mobile money » (mode SANDBOX).
 *
 * Identifiants et secret de callback exclusivement en configuration/env
 * (`config/restaurantmanager.php`) — aucun secret en dur (critère
 * d'acceptation). En sandbox, l'initiation retourne un paiement `pending`
 * avec une référence locale ; la confirmation arrive par le callback signé
 * HMAC (RESTO-407). `verify` reste `pending` tant que le callback n'a pas
 * confirmé (le callback écrit directement le statut sur le paiement).
 */
final class MobileMoneyPaymentGateway implements PaymentGatewayInterface
{
    public function providerCode(): string
    {
        return 'mobile_money';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        if (! config('restaurantmanager.mobile_money.sandbox', true)) {
            // Hors sandbox : le provider (PVIT/Orange Money) serait appelé ici.
            throw new PaymentGatewayException(
                'Mobile money production provider is not configured yet.',
                'provider_unreachable',
            );
        }

        return new InitiatePaymentResult(
            status: PaymentStatus::PENDING,
            providerReference: 'MM-'.strtoupper((string) Str::uuid()),
            message: 'Paiement mobile money en attente de confirmation (callback signé).',
        );
    }

    public function verify(VerifyPaymentRequest $request): PaymentStatus
    {
        // En sandbox, l'état réel est porté par le paiement (callback).
        // Le provider production serait interrogé ici.
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
}
