<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course VTC annulée (BC-35 VTC, VTC-03/#8359).
 *
 * Motif tracé (spec §5.2) : passager avant acceptation, chauffeur ou
 * exploitant ensuite. La cascade d'offres s'arrête via les gardes de
 * statut du dispatch (qui n'offre qu'en `dispatching`) ; #8464 — la
 * partie non actrice de l'annulation est notifiée
 * (`NotifyVtcRideCancelledListener`).
 */
class VtcRideCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly string $reference,
        public readonly string $reason,
        public readonly string $cancelledBy,
    ) {}
}
