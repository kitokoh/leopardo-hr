<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Carbon\CarbonImmutable;

/**
 * TRAVEL-808 (#6098) / TRAVEL-813 (#6103) — Calcul des pénalités de
 * remboursement (règles d'élasticité).
 *
 * Règle par défaut (élasticité, en heures avant le départ) :
 *   - ≥ 24 h → pénalité 0 % ;
 *   - 12 h à 24 h → 10 % ;
 *   - < 12 h → 25 %.
 * Si une politique d'annulation configurable (travel_cancellation_policies,
 * TRAVEL-813) couvre le trajet/classe, elle surclasse la règle par défaut
 * (fenêtre la plus contraignante : pénalité la plus élevée applicable).
 */
final class TravelRefundPolicyResolver
{
    public function penaltyPercent(TravelBooking $booking, int $classId): int
    {
        $trip = $booking->trip;

        if (! $trip instanceof TravelTrip) {
            return 0;
        }

        $departure = CarbonImmutable::parse(
            $trip->departure_date->toDateString().' '.($trip->departure_time ?? '00:00'),
        );

        $hoursBefore = CarbonImmutable::now()->diffInHours($departure, false);

        $policy = $this->applicablePolicy($booking->trip, $classId);

        if ($policy instanceof TravelCancellationPolicy) {
            if (! $policy->refundable) {
                return 100;
            }

            if ($hoursBefore < $policy->hours_before_departure) {
                return $policy->penalty_percent;
            }

            return 0;
        }

        if ($hoursBefore >= 24) {
            return 0;
        }

        if ($hoursBefore >= 12) {
            return 10;
        }

        return 25;
    }

    private function applicablePolicy(?TravelTrip $trip, int $classId): ?TravelCancellationPolicy
    {
        if (! $trip instanceof TravelTrip) {
            return null;
        }

        // Politique la plus spécifique d'abord : (trajet, classe) > (classe)
        // > (trajet) > défaut tenant.
        $candidates = [
            ['trip_id' => $trip->id, 'class_id' => $classId],
            ['trip_id' => null, 'class_id' => $classId],
            ['trip_id' => $trip->id, 'class_id' => null],
            ['trip_id' => null, 'class_id' => null],
        ];

        foreach ($candidates as $candidate) {
            $query = TravelCancellationPolicy::query();
            foreach ($candidate as $column => $value) {
                if ($value === null) {
                    $query->whereNull($column);
                } else {
                    $query->where($column, $value);
                }
            }
            $policy = $query->first();
            if ($policy instanceof TravelCancellationPolicy) {
                return $policy;
            }
        }

        return null;
    }
}
