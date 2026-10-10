<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideCancelled;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Annulation d'une course VTC par le PASSAGER (BC-34 VTC, VTC-03/#8359).
 *
 * Règle métier (spec §5.2) : le passager ne peut annuler qu'AVANT
 * l'acceptation par un chauffeur (requested/dispatching) — après, la course
 * appartient au cycle chauffeur/dispatcher. Le motif est toujours tracé
 * (colonne + journal append-only + événement de domaine, écouté par le
 * dispatch VTC-04 pour stopper la cascade d'offres).
 */
final class CancelRideAction
{
    public function __construct(
        private readonly VtcRideStateMachine $stateMachine,
    ) {
    }

    /**
     * @throws InvalidRideTransitionException
     */
    public function execute(VtcRide $ride, string $reason, string $cancelledBy = 'passenger'): VtcRide
    {
        $from = $ride->status;

        // Règle passager : annulation avant accepted uniquement.
        if (! in_array($from, [VtcRideStatus::Requested, VtcRideStatus::Dispatching], true)) {
            throw new InvalidRideTransitionException($from, VtcRideStatus::Cancelled);
        }

        $this->stateMachine->assertCanTransitionTo($from, VtcRideStatus::Cancelled);

        DB::transaction(function () use ($ride, $reason, $cancelledBy): void {
            $ride->forceFill([
                'status' => VtcRideStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            VtcRideEvent::create([
                'ride_id' => $ride->id,
                'type' => VtcRideEventType::RideCancelled->value,
                'payload' => [
                    'reason' => $reason,
                    'cancelled_by' => $cancelledBy,
                ],
            ]);
        });

        VtcRideCancelled::dispatch(
            (string) $ride->company_id,
            $ride->id,
            $ride->reference,
            $reason,
            $cancelledBy,
        );

        return $ride->refresh();
    }
}
