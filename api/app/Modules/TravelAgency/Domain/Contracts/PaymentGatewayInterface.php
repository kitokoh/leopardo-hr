<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * TRAVEL-405 (#6057) — Contrat de passerelle de paiement TravelAgency.
 *
 * Spec §8.2 : initiation, vérification/re-conciliation active, remboursement.
 * Les adaptateurs concrets (Cash, PVIT sandbox) vivent dans
 * `Infrastructure/Services/Payment/` et sont résolus par
 * `PaymentGatewayRegistry`.
 */
interface PaymentGatewayInterface
{
    /**
     * Initie un paiement auprès du provider.
     *
     * @param  array{
     *     booking_reference: string,
     *     amount_minor: int,
     *     currency: string,
     *     channel?: string|null,
     *     idempotency_key: string
     * }  $request
     * @return array{
     *     provider_reference: string,
     *     redirect_url: string|null,
     *     status: string
     * }
     */
    public function initiate(array $request): array;

    /**
     * Vérifie l'état d'un paiement auprès du provider (re-conciliation
     * active, idempotente, retry/backoff borné).
     *
     * @return array{status: string, paid_at?: string|null}
     */
    public function verify(string $providerReference): array;

    /**
     * Rembourse un paiement (réservé `travel.manage`).
     *
     * @return array{status: string, refunded_at?: string|null}
     */
    public function refund(string $providerReference): array;
}
