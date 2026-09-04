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
 * RESTO-406 (#6193) — Adapter « carte bancaire » (terminal local).
 *
 * Le paiement est confirmé localement par le terminal (l'approbation bancaire
 * est déjà acquise) ; la référence de transaction est générée pour la
 * traçabilité et le remboursement.
 */
final class CardPaymentGateway implements PaymentGatewayInterface
{
    public function providerCode(): string
    {
        return 'card';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult
    {
        return new InitiatePaymentResult(
            status: PaymentStatus::CONFIRMED,
            providerReference: 'CARD-'.strtoupper((string) Str::uuid()),
            message: 'Paiement par carte confirmé par le terminal.',
        );
    }

    public function verify(VerifyPaymentRequest $request): PaymentStatus
    {
        return str_starts_with($request->providerReference, 'CARD-')
            ? PaymentStatus::CONFIRMED
            : PaymentStatus::FAILED;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            providerReference: 'CARD-REFUND-'.strtoupper((string) Str::uuid()),
            message: 'Remboursement carte traité.',
        );
    }
}
