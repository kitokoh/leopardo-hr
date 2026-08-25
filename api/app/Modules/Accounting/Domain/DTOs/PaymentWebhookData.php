<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\DTOs;

/**
 * #5272 — Informations de paiement extraites d'un webhook passerelle
 * (payload vérifié) par l'adaptateur. Permet au service de rapprochement de
 * rester indépendant de la forme exacte des payloads Chargily/Stripe.
 */
final readonly class PaymentWebhookData
{
    public function __construct(
        /** Identifiant externe du paiement côté passerelle (idempotence). */
        public string $gatewayPaymentId,
        /** Montant reçu, en unité mineure de la devise (centimes, ou unité pour les devises 0 décimale). */
        public int $amountMinor,
        /** Devise ISO 4217 en majuscules (DZD, EUR, XOF…). */
        public string $currency,
        /** paid | cancelled | other — seul `paid` déclenche le rapprochement. */
        public string $eventType,
        /** Document comptable cible (metadata envoyée au checkout). */
        public ?int $documentId,
        /** Tenant cible (metadata envoyée au checkout). */
        public ?string $companyId,
        /** Méthode de règlement enregistrée (online_chargily | online_stripe). */
        public string $method,
    ) {}
}
