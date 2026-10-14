<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Listeners;

use App\Modules\Vtc\Domain\Events\VtcRideAccepted;
use App\Modules\Vtc\Infrastructure\Services\VtcRideNotificationService;
use Throwable;

/**
 * #8423 (M-20) — VtcRideAccepted n'était écouté par PERSONNE (événement
 * orphelin) : le passager est désormais notifié qu'un chauffeur a accepté
 * sa course.
 *
 * DÉGRADATION GRACIEUSE : toute défaillance de notification est reportée,
 * jamais propagée — l'acceptation de la course ne doit JAMAIS échouer
 * parce que le push est indisponible (« mail/FCM indispo ≠ échec course »).
 */
final class NotifyVtcRideAcceptedListener
{
    public function __construct(
        private readonly VtcRideNotificationService $notifications,
    ) {}

    public function handle(VtcRideAccepted $event): void
    {
        try {
            $this->notifications->notifyAccepted($event->companyId, $event->rideId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
