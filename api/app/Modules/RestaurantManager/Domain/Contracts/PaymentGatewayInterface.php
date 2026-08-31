<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentResult;
use App\Modules\RestaurantManager\Domain\Payments\RefundRequest;
use App\Modules\RestaurantManager\Domain\Payments\RefundResult;
use App\Modules\RestaurantManager\Domain\Payments\VerifyPaymentRequest;

/**
 * RESTO-406 (#6193) — Contrat de passerelle de paiement de la verticale
 * RestaurantManager (pattern Travel, spec §6.1).
 *
 * Adapters v1 :
 * - `CashPaymentGateway` : espèces, confirmation manuelle par un serveur
 *   autorisé (confirmation synchrone) ;
 * - `CardPaymentGateway` : terminal local, paiement confirmé localement ;
 * - `MobileMoneyPaymentGateway` : sandbox (identifiants en config), confirmé
 *   par callback signé (RESTO-407).
 *
 * Règles : aucun secret en dur (config/env), erreurs normalisées
 * (PaymentGatewayException), montants en minor units.
 */
interface PaymentGatewayInterface
{
    public function providerCode(): string;

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult;

    public function verify(VerifyPaymentRequest $request): PaymentStatus;

    public function refund(RefundRequest $request): RefundResult;
}
