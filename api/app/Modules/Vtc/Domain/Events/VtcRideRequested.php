<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course VTC demandée (BC-34 VTC, VTC-03/#8359).
 *
 * Événement de découplage : le dispatch (VTC-04) écoute cet événement pour
 * lancer le matching du chauffeur le plus proche ; les intégrations futures
 * (notifications passager, analytics) s'y brancheront sans couplage direct
 * (pattern événements inter-modules, spec §2).
 */
class VtcRideRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly string $reference,
        public readonly ?int $passengerUserId,
    ) {
    }
}
