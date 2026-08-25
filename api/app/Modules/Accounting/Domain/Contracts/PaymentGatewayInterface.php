<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Contracts;

use App\Modules\Accounting\Domain\DTOs\PaymentCheckout;
use App\Modules\Accounting\Domain\DTOs\PaymentWebhookData;
use App\Modules\Accounting\Domain\Models\AccountingDocument;

/**
 * #5272 — Contrat des passerelles de paiement en ligne des documents
 * comptables (ADR-0017, option A : dual-PSP piloté par pays entreprise).
 *
 * Implémentations : ChargilyPaymentGateway (DZ), StripePaymentGateway
 * (FR/UK/US/CI). MA/TN/TR accueilleront des passerelles locales en phase 2
 * derrière la même interface.
 */
interface PaymentGatewayInterface
{
    /** Nom canonique de la passerelle (chargily | stripe). */
    public function gatewayName(): string;

    /**
     * La passerelle est-elle configurée (clé API présente) ? Fail-closed :
     * une passerelle non configurée refuse tout checkout.
     */
    public function isConfigured(): bool;

    /**
     * Initie une session de paiement pour le solde restant du document.
     *
     * @param  float  $amount  montant à encaisser (unité monétaire, ex. DZD)
     */
    public function createCheckout(
        AccountingDocument $document,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): PaymentCheckout;

    /**
     * Vérifie la signature HMAC du webhook (fail-closed : secret absent ou
     * signature invalide → null) et retourne le payload décodé.
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array;

    /**
     * Extrait les informations de paiement d'un payload vérifié.
     * Retourne null pour un payload sans objet de paiement exploitable.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?PaymentWebhookData;
}
