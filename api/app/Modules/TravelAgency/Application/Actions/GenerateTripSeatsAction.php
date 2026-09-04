<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Support\Facades\DB;

/**
 * Génération transactionnelle de l'inventaire des sièges d'un trajet
 * (TRAVEL-208, issue #6021).
 *
 * Crée exactement `$trip->total_seats` lignes `travel_trip_seats`, en une
 * seule transaction (échec partiel = rollback complet). Idempotente : si le
 * trajet possède déjà des sièges (rejeu de la création), l'action ne fait
 * rien — la contrainte d'unicité `(company_id, trip_id, seat_number)`
 * garantit qu'aucun doublon ne peut être introduit même en cas de course.
 */
final class GenerateTripSeatsAction
{
    public function execute(TravelTrip $trip): void
    {
        if (TravelTripSeat::query()->where('trip_id', $trip->id)->exists()) {
            // Rejeu de la création (retry, job relancé…) : les sièges existent
            // déjà, on ne régénère jamais un inventaire déjà constitué.
            return;
        }

        DB::transaction(function () use ($trip): void {
            for ($seatNumber = 1; $seatNumber <= $trip->total_seats; $seatNumber++) {
                TravelTripSeat::query()->create([
                    'trip_id' => $trip->id,
                    'seat_number' => $seatNumber,
                    'status' => SeatStatus::FREE->value,
                ]);
            }
        });
    }
}
