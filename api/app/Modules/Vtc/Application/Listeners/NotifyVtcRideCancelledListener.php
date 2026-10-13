<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Listeners;

use App\Modules\Vtc\Domain\Events\VtcRideCancelled;
use App\Modules\Vtc\Infrastructure\Services\VtcRideNotificationService;
use Throwable;

/**
 * #8464 — `VtcRideCancelled` n'était écouté par PERSONNE : un passager dont
 * la course était annulée par le chauffeur, un dispatcher ou un admin
 * restait muet. La partie non actrice de l'annulation est désormais
 * notifiée (passager et/ou chauffeur assigné).
 *
 * DÉGRADATION GRACIEUSE : toute défaillance de notification est reportée,
 * jamais propagée — l'annulation de la course ne doit JAMAIS échouer
 * parce que le push est indisponible.
 */
final class NotifyVtcRideCancelledListener
{
    public function __construct(
        private readonly VtcRideNotificationService $notifications,
    ) {}

    public function handle(VtcRideCancelled $event): void
    {
        try {
            $this->notifications->notifyCancelled($event->companyId, $event->rideId, $event->cancelledBy);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
