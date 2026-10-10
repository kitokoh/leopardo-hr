<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course VTC annulée (BC-34 VTC, VTC-03/#8359).
 *
 * Motif tracé (spec §5.2) : passager avant acceptation, chauffeur ou
 * exploitant ensuite — le dispatch (VTC-04) l'écoute pour stopper la
 * cascade d'offres en cours.
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
    ) {
    }
}
