<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * TRAVEL-809 (#6099) — Correspondances (recherche multi-trajets).
 *
 * Une correspondance n'est VALIDE que si les horaires sont compatibles :
 * arrivée de la jambe 1 + délai minimal de correspondance ≤ départ de la
 * jambe 2 (critère d'acceptation). Vente groupée = deux réservations
 * indépendantes (billets séparés) reliées par `connection_group_id`.
 */
final class TravelConnectionService
{
    public function __construct(private readonly CreateBookingAction $createBooking) {}

    /**
     * Recherche des correspondances compatibles pour une date.
     *
     * @return list<array{
     *     leg1: TravelTrip,
     *     leg2: TravelTrip,
     *     connection_minutes: int,
     *     total_price_minor: int,
     *     currency: string
     * }>
     */
    public function search(
        string $companyId,
        int $originCityId,
        int $destinationCityId,
        string $date,
        int $minConnectionMinutes = 45,
    ): array {
        /** @var list<TravelTrip> $firstLegs */
        $firstLegs = TravelTrip::query()
            ->with(['route', 'prices'])
            ->where('company_id', $companyId)
            ->where('status', TripStatus::PUBLISHED)
            ->whereDate('departure_date', $date)
            ->whereHas('route', fn ($q) => $q->where('origin_city_id', $originCityId))
            ->get()
            ->all();

        if ($firstLegs === []) {
            return [];
        }

        $hubCityIds = array_values(array_unique(array_filter(array_map(
            fn (TravelTrip $trip): ?int => $trip->route?->destination_city_id,
            $firstLegs,
        ))));

        /** @var list<TravelTrip> $secondLegs */
        $secondLegs = TravelTrip::query()
            ->with(['route', 'prices'])
            ->where('company_id', $companyId)
            ->where('status', TripStatus::PUBLISHED)
            ->whereIn('departure_date', [Carbon::parse($date)->toDateString(), Carbon::parse($date)->addDay()->toDateString()])
            ->whereHas('route', function ($q) use ($hubCityIds, $destinationCityId): void {
                $q->whereIn('origin_city_id', $hubCityIds)->where('destination_city_id', $destinationCityId);
            })
            ->get()
            ->all();

        $results = [];

        foreach ($firstLegs as $leg1) {
            $leg1Arrival = $this->departureDateTime($leg1, 'arrival');

            if ($leg1Arrival === null) {
                continue;
            }

            foreach ($secondLegs as $leg2) {
                if ($leg1->route?->destination_city_id !== $leg2->route?->origin_city_id) {
                    continue;
                }

                $leg2Departure = $this->departureDateTime($leg2, 'departure');

                if ($leg2Departure === null) {
                    continue;
                }

                $connectionMinutes = (int) $leg1Arrival->diffInMinutes($leg2Departure);

                if ($connectionMinutes < $minConnectionMinutes) {
                    continue; // Horaires incompatibles.
                }

                $price1 = (int) ($leg1->prices->min('adult_price_minor') ?? PHP_INT_MAX);
                $price2 = (int) ($leg2->prices->min('adult_price_minor') ?? PHP_INT_MAX);

                if ($price1 === PHP_INT_MAX || $price2 === PHP_INT_MAX) {
                    continue;
                }

                $results[] = [
                    'leg1' => $leg1,
                    'leg2' => $leg2,
                    'connection_minutes' => $connectionMinutes,
                    'total_price_minor' => $price1 + $price2,
                    'currency' => (string) ($leg1->prices->first()?->currency ?? 'XAF'),
                ];
            }
        }

        usort($results, fn (array $a, array $b): int => $a['total_price_minor'] <=> $b['total_price_minor']);

        return $results;
    }

    /**
     * Vente groupée : deux réservations liées par `connection_group_id`.
     *
     * @param  list<array<string, mixed>>  $passengersLeg1
     * @param  list<array<string, mixed>>  $passengersLeg2
     * @return array{leg1: TravelBooking, leg2: TravelBooking}
     */
    public function book(
        TravelTrip $leg1,
        TravelTrip $leg2,
        array $passengersLeg1,
        array $passengersLeg2,
        Employee $actor,
        string $idempotencyKey,
    ): array {
        $group = (string) Str::uuid();

        $booking1 = $this->createBooking->execute(
            trip: $leg1,
            passengers: $passengersLeg1,
            source: BookingSource::ONLINE,
            actor: $actor,
            idempotencyKey: $idempotencyKey.':leg1',
            connectionGroupId: $group,
        );

        $booking2 = $this->createBooking->execute(
            trip: $leg2,
            passengers: $passengersLeg2,
            source: BookingSource::ONLINE,
            actor: $actor,
            idempotencyKey: $idempotencyKey.':leg2',
            connectionGroupId: $group,
        );

        return ['leg1' => $booking1, 'leg2' => $booking2];
    }

    private function departureDateTime(TravelTrip $trip, string $field): ?Carbon
    {
        $date = $field === 'arrival' ? $trip->arrival_date : $trip->departure_date;
        $time = $field === 'arrival' ? $trip->arrival_time : $trip->departure_time;

        if ($date === null) {
            return null;
        }

        return $date->copy()->setTimeFromTimeString((string) ($time ?? '00:00'));
    }
}
