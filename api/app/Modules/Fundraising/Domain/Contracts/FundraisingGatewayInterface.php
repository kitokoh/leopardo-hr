<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Contracts;

use App\Modules\Fundraising\Application\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Application\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;

/**
 * Contrat des passerelles de paiement des contributions (verticale
 * FUNDRAISING — spec SOLUTION_FUNDRAISING.md §4.1).
 *
 * Inspiré de l'ADR-0017 Accounting (`PaymentGatewayInterface`, dual-PSP
 * fail-closed) et de TRAVEL-407 (`PvitPaymentGateway` sandbox).
 *
 * Implémentations v1 : `StripeContributionGateway` (carte),
 * `MobileMoneyGateway` (agrégateur config-driven, sandbox USSD/push),
 * `ManualGateway` (espèces/virement confirmé par le responsable).
 * Fail-closed : une passerelle non configurée refuse toute initiation —
 * jamais de fallback silencieux.
 */
interface FundraisingGatewayInterface
{
    /** Nom canonique de la passerelle (stripe | mobile_money | manual). */
    public function gatewayName(): string;

    /**
     * La passerelle est-elle configurée ? Fail-closed : non configurée ⇒
     * `InitiateContribution` lève PAYMENT_GATEWAY_NOT_CONFIGURED.
     */
    public function isConfigured(): bool;

    /**
     * Initie le paiement d'une contribution `pending`.
     */
    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation;

    /**
     * Vérifie la signature du webhook (fail-closed : secret absent ou
     * signature invalide → null) et retourne le payload décodé.
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array;

    /**
     * Extrait la mise à jour de paiement d'un payload vérifié.
     * Retourne null pour un payload sans événement exploitable.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate;

    /**
     * Vérification active (re-conciliation mobile money quand le webhook
     * tarde). Retourne null si le provider ne sait pas répondre.
     */
    public function verify(string $providerReference): ?GatewayPaymentUpdate;
}
