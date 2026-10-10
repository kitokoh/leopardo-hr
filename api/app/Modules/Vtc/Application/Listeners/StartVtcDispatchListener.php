<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Listeners;

use App\Modules\Vtc\Domain\Events\VtcRideRequested;
use App\Modules\Vtc\Infrastructure\Jobs\OfferRideToNearestDriverJob;

/**
 * Démarrage du dispatch sur demande de course (BC-34 VTC, VTC-04/#8360).
 *
 * Découplage par événement (spec §2) : RequestRideAction émet
 * VtcRideRequested, ce listener enfile le job de matching — la création de
 * course reste synchrone et légère, la cascade d'offres vit dans la file
 * `vtc`.
 */
final class StartVtcDispatchListener
{
    public function handle(VtcRideRequested $event): void
    {
        OfferRideToNearestDriverJob::dispatch($event->companyId, $event->rideId);
    }
}
