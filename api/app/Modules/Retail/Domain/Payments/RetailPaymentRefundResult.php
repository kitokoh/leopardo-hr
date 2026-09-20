<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Payments;

/**
 * Resultat d'une demande de remboursement provider (BC-17 RETAIL, #7812).
 *
 * `succeeded = true` quand le provider a accepte le remboursement (l'intent
 * passe alors a `refunded`). `payload` est conserve sur l'intent comme
 * trace auditable.
 */
final readonly class RetailPaymentRefundResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $succeeded,
        public array $payload = [],
    ) {}
}
