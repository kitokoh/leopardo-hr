<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatch VTC épuisé — course expirée (BC-34 VTC, VTC-04/#8360).
 *
 * Aucun chauffeur disponible n'a accepté dans la fenêtre (cascade d'offres
 * épuisée ou rayon sans candidat) : le passager doit être notifié
 * (intégration future) et peut relancer une demande.
 */
class VtcRideExpired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly string $reference,
        public readonly string $reason,
    ) {}
}
