<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-813 (#6103) — Résolution et application des politiques
 * d'annulation.
 *
 * La pénalité est TOUJOURS calculée serveur (jamais acceptée du client) :
 *   - résolution par spécificité : (trajet, classe) → (trajet) →
 *     (classe) → défaut tenant ;
 *   - `cancel_before_hours` : la pénalité ne s'applique que si l'annulation
 *     survient moins de N heures avant le départ (null = toujours) ;
 *   - `refundable` : détermine si un remboursement est possible.
 */
final class TravelCancellationPolicyService
{
    public function resolveFor(TravelTrip $trip, ?int $classId): ?TravelCancellationPolicy
    {
        /** @var TravelCancellationPolicy|null $policy */
        $policy = TravelCancellationPolicy::query()
            ->where('company_id', $trip->company_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('trip_id', $trip->id)->orWhereNull('trip_id'))
            ->where(fn ($q) => $q->where('class_id', $classId)->orWhereNull('class_id'))
            ->orderByRaw(
                'CASE WHEN trip_id IS NOT NULL THEN 1 ELSE 0 END DESC, '
                .'CASE WHEN class_id IS NOT NULL THEN 1 ELSE 0 END DESC',
            )
            ->orderByDesc('id')
            ->first();

        return $policy;
    }

    /**
     * Pénalité applicable (0..100) pour une annulation à `$now`.
     *
     * @return array{policy: TravelCancellationPolicy|null, penalty_percent: int, applies: bool}
     */
    public function penaltyFor(
        TravelTrip $trip,
        ?int $classId,
        DateTimeInterface $departureAt,
        ?DateTimeInterface $now = null,
    ): array {
        $policy = $this->resolveFor($trip, $classId);
        $now = CarbonImmutable::parse($now ?? now());

        if (! $policy instanceof TravelCancellationPolicy) {
            return ['policy' => null, 'penalty_percent' => 0, 'applies' => true];
        }

        $hoursUntilDeparture = $now->diffInHours($departureAt, false);
        $withinDeadline = $policy->cancel_before_hours === null
            || $hoursUntilDeparture < $policy->cancel_before_hours;

        if (! $withinDeadline) {
            return ['policy' => $policy, 'penalty_percent' => 0, 'applies' => false];
        }

        return ['policy' => $policy, 'penalty_percent' => $policy->penalty_percent, 'applies' => true];
    }

    /**
     * Détail du remboursement d'un passager (montants en unités mineures).
     *
     * @return array{refundable: bool, refund_amount_minor: int, penalty_minor: int, policy_id: int|null}
     */
    public function refundBreakdownForPassenger(
        TravelPassenger $passenger,
        DateTimeInterface $departureAt,
        ?DateTimeInterface $now = null,
    ): array {
        $trip = $passenger->booking?->trip;

        if (! $trip instanceof TravelTrip) {
            return [
                'refundable' => false,
                'refund_amount_minor' => 0,
                'penalty_minor' => 0,
                'policy_id' => null,
            ];
        }

        $penalty = $this->penaltyFor($trip, $passenger->class_id, $departureAt, $now);
        $policy = $penalty['policy'];

        if ($policy instanceof TravelCancellationPolicy && ! $policy->refundable) {
            return [
                'refundable' => false,
                'refund_amount_minor' => 0,
                'penalty_minor' => $passenger->unit_price_minor,
                'policy_id' => $policy->id,
            ];
        }

        $unitPrice = (int) $passenger->unit_price_minor;
        $penaltyMinor = (int) round($unitPrice * $penalty['penalty_percent'] / 100);

        return [
            'refundable' => true,
            'refund_amount_minor' => max(0, $unitPrice - $penaltyMinor),
            'penalty_minor' => $penaltyMinor,
            'policy_id' => $policy?->id,
        ];
    }

    /**
     * Remboursement total d'une réservation (tous passagers).
     *
     * @return array{refundable: bool, refund_amount_minor: int, penalty_minor: int}
     */
    public function refundBreakdownForBooking(TravelBooking $booking, ?DateTimeInterface $now = null): array
    {
        $trip = $booking->trip;

        if (! $trip instanceof TravelTrip) {
            return ['refundable' => false, 'refund_amount_minor' => 0, 'penalty_minor' => 0];
        }

        $departureAt = $trip->departure_date->copy()
            ->setTimeFromTimeString((string) ($trip->departure_time ?? '00:00'));

        if (! $departureAt instanceof Carbon) {
            $departureAt = now()->addDay();
        }

        $total = 0;
        $penalty = 0;
        $refundable = true;

        foreach ($booking->passengers as $passenger) {
            $breakdown = $this->refundBreakdownForPassenger($passenger, $departureAt, $now);
            $total += $breakdown['refund_amount_minor'];
            $penalty += $breakdown['penalty_minor'];
            $refundable = $refundable && $breakdown['refundable'];
        }

        return ['refundable' => $refundable, 'refund_amount_minor' => $total, 'penalty_minor' => $penalty];
    }
}
