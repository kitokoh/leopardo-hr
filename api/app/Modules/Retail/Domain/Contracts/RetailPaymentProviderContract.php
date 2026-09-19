<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Contracts;

/**
 * Port PSP du paiement en ligne marketplace (BC-17 RETAIL, #7812).
 *
 * Contrat vers le chantier BC-21 PAYMENTS (encaissement mobile money / carte).
 * Tant que les profils de paiement tenant BC-21 (branche
 * bc/bc21-paiements-encaissement) ne sont pas mergés, l'implémentation liée
 * est un seam journalisé (`LoggingRetailPaymentProviderAdapter`) — même
 * pattern que les contrats BC-08/BC-13 du module Delivery
 * (DELIVERY-205/206). Le webhook signé (HMAC-SHA256, pattern
 * ChargilyService #2615 fail-closed) reste le SEUL chemin de confirmation.
 */
interface RetailPaymentProviderContract
{
    /**
     * Initialise un encaissement chez le PSP et retourne la référence
     * provider (unique) et l'URL de paiement hébergée (null si le provider
     * n'en fournit pas).
     *
     * @return array{provider: string, provider_reference: string, checkout_url: string|null}
     */
    public function initiate(
        string $companyId,
        string $orderReference,
        int $amountMinor,
        string $currency,
        ?string $customerPhone,
    ): array;
}
