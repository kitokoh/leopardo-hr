<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Payments;

use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;

/**
 * Evenement webhook normalise d'un provider de paiement Retail
 * (BC-17 RETAIL, #7812).
 *
 * `intentReference` est la reference LOCALE de l'intent (metadata renvoyee
 * par le PSP) ; `status` est le statut cible normalise (succeeded, failed,
 * expired) ou null pour un type d'evenement ignore. `amountMinor` /
 * `currency` permettent le controle anti-fraude du montant notifie.
 */
final readonly class RetailPaymentWebhookEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $intentReference,
        public ?RetailPaymentIntentStatus $status,
        public ?int $amountMinor,
        public ?string $currency,
        public array $raw = [],
    ) {}
}
