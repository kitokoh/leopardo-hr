<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Listeners;

use App\Modules\Vtc\Domain\Events\VtcRideCompleted;
use App\Modules\Vtc\Infrastructure\Services\VtcRideNotificationService;
use Throwable;

/**
 * #8423 (M-20) — VtcRideCompleted n'était écouté par PERSONNE (événement
 * orphelin) : le passager reçoit la clôture avec le montant final, le
 * chauffeur son récapitulatif.
 *
 * DÉGRADATION GRACIEUSE : toute défaillance de notification est reportée,
 * jamais propagée — la clôture (prix final, libération du chauffeur) est
 * déjà persistée, le push n'est qu'un confort.
 */
final class NotifyVtcRideCompletedListener
{
    public function __construct(
        private readonly VtcRideNotificationService $notifications,
    ) {}

    public function handle(VtcRideCompleted $event): void
    {
        try {
            $this->notifications->notifyCompleted($event->companyId, $event->rideId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
