<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways;

use App\Modules\RestaurantManager\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentResult;
use App\Modules\RestaurantManager\Domain\Payments\RefundRequest;
use App\Modules\RestaurantManager\Domain\Payments\RefundResult;
use App\Modules\RestaurantManager\Domain\Payments\VerifyPaymentRequest;
use Illuminate\Support\Str;

/**
 * RESTO-406 (#6193) — Adapter « espèces » (cash).
 *
 * Confirmation manuelle par un serveur/caissier autorisé : l'initiation
 * confirme immédiatement le paiement (l'encaissement physique est constaté
 * par l'utilisateur) et génère une référence de traçabilité locale.
 */
final class CashPaymentGateway implements PaymentGatewayInterface
{
    public function providerCode(): string
    {
        return 'cash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        return new InitiatePaymentResult(
            status: PaymentStatus::CONFIRMED,
            providerReference: 'CASH-'.strtoupper((string) Str::uuid()),
            message: 'Paiement en espèces confirmé.',
        );
    }

    public function verify(VerifyPaymentRequest $request): PaymentStatus
    {
        return str_starts_with($request->providerReference, 'CASH-')
            ? PaymentStatus::CONFIRMED
            : PaymentStatus::FAILED;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            providerReference: 'CASH-REFUND-'.strtoupper((string) Str::uuid()),
            message: 'Remboursement espèces traité.',
        );
    }
}
