<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Listeners;

use App\Modules\Vtc\Domain\Events\VtcRideExpired;
use App\Modules\Vtc\Infrastructure\Services\VtcRideNotificationService;
use Throwable;

/**
 * #8423 (M-20) — VtcRideExpired n'était écouté par PERSONNE (événement
 * orphelin) : le passager est désormais informé que sa course n'a pas
 * trouvé de chauffeur (invitation à réessayer) — fin de l'attente muette.
 *
 * DÉGRADATION GRACIEUSE : toute défaillance de notification est reportée,
 * jamais propagée — l'expiration de la course est déjà actée, le push
 * n'est qu'un confort.
 */
final class NotifyVtcRideExpiredListener
{
    public function __construct(
        private readonly VtcRideNotificationService $notifications,
    ) {}

    public function handle(VtcRideExpired $event): void
    {
        try {
            $this->notifications->notifyExpired($event->companyId, $event->rideId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
