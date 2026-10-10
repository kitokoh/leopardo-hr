<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\DTOs;

/**
 * Mise à jour de paiement extraite d'un webhook vérifié ou d'une
 * vérification active (verticale FUNDRAISING — spec §4.1).
 *
 * `eventId` unique par provider : clé d'idempotence
 * (`fundraising_payment_events`). `paid` true ⇒ passage `pending →
 * completed` + crédit du compteur (transaction unique) ; false ⇒ `failed`.
 */
final readonly class GatewayPaymentUpdate
{
    public function __construct(
        public string $eventId,
        public string $providerReference,
        public bool $paid,
        public ?string $paidAt,
    ) {}
}
