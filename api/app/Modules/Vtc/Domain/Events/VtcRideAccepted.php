<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course VTC acceptée par un chauffeur (BC-34 VTC, VTC-04/#8360).
 *
 * Émise sous verrou après la transition dispatching → accepted : la cascade
 * d'offres est close, le passager peut être notifié (intégrations futures —
 * découplage par événement, spec §2).
 */
class VtcRideAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly string $reference,
        public readonly int $driverId,
    ) {}
}
