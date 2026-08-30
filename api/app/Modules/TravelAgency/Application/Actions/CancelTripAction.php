<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-310 (#6040) — Annulation d'un trajet (→ cancelled, terminal).
 *
 * Transactionnelle : transition validée vers `cancelled`, événement outbox
 * `travel.trip.cancelled.v1` publié APRÈS commit. Le motif est obligatoire
 * (Request) et conservé dans le payload (audit). Un trajet déjà annulé est
 * idempotent.
 */
final class CancelTripAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  string  $reason  motif d'annulation (obligatoire, validé par la Request)
     */
    public function execute(TravelTrip $trip, Employee $actor, string $reason): TravelTrip
    {
        if ($trip->status === TripStatus::CANCELLED) {
            return $trip; // Idempotence : rejeu sans effet de bord.
        }

        DB::transaction(function () use ($trip): void {
            $trip->forceFill(['status' => TripStatus::CANCELLED])->save();
        });

        $this->outbox->publish($trip->company_id, 'travel.trip.cancelled.v1', [
            'trip_id' => $trip->id,
            'trip_code' => $trip->code,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => $reason,
        ]);

        return $trip;
    }
}
