<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\DTOs;

use App\Modules\Paiements\Domain\Models\PaymentWebhookEvent;

/**
 * BC-38 PAIEMENTS (#8742) — verdict d'application d'un webhook entrant.
 *
 * Le controleur a besoin de deux informations distinctes : la ligne journalisee
 * (pour l'audit) et le fait qu'il s'agissait d'un **rejeu** (pour le signaler au
 * fournisseur). Les confondre obligerait a deviner le rejeu depuis `outcome`,
 * or un rejeu d'un evenement deja `out_of_sequence` doit rester identifiable
 * comme un rejeu — c'est ce que le fournisseur utilise pour arreter de
 * reessayer.
 */
final readonly class WebhookApplicationResult
{
    public function __construct(
        public PaymentWebhookEvent $event,
        public bool $replayed,
    ) {}

    public function outcome(): string
    {
        return $this->event->outcome;
    }
}
