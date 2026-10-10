<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course VTC clôturée (BC-34 VTC, VTC-05/#8361).
 *
 * Émise à la transition in_progress → completed avec le prix final (minor
 * units) recalculé sur le trajet réel — point d'intégration prévu pour la
 * facturation (hors scope v1, spec §11) et les notifications passager.
 */
class VtcRideCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly string $reference,
        public readonly int $driverId,
        public readonly ?int $finalPriceMinor,
        public readonly string $currency,
    ) {
    }
}
