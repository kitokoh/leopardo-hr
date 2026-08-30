<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * TRAVEL-809 (#6099) — Recherche de correspondances (multi-trajets).
 *
 * Combine un trajet A (origine → ville intermédiaire) et un trajet B
 * (ville intermédiaire → destination) dont les horaires sont compatibles :
 * départ(B) ≥ arrivée(A) + MIN_CONNECTION_MINUTES. Une correspondance n'est
 * valide QUE si cette contrainte est respectée (acceptance TRAVEL-809).
 * Prix total = somme des tarifs adultes minimum des deux trajets.
 */
final class ConnectionSearchAction
{
    /** Correspondance minimale entre deux trajets (minutes). */
    public const MIN_CONNECTION_MINUTES = 45;

    /**
     * @return list<array{first: TravelTrip, second: TravelTrip, total_price_minor: int, connection_minutes: int}>
     */
    public function search(int $originCityId, int $destinationCityId, string $date): array
    {
        $firstLegs = $this->legs($originCityId, $date, isOrigin: true);
        $secondLegs = $this->legs($destinationCityId, $date, isOrigin: false);

        if ($firstLegs->isEmpty() || $secondLegs->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($firstLegs as $first) {
            foreach ($secondLegs as $second) {
                if ($first->route->destination_city_id !== $second->route->origin_city_id) {
                    continue;
                }

                $arrivalA = $this->datetime($first, useArrival: true);
                $departureB = $this->datetime($second, useArrival: false);

                $connectionMinutes = (int) $arrivalA->diffInMinutes($departureB, false);

                if ($connectionMinutes < self::MIN_CONNECTION_MINUTES) {
                    continue;
                }

                $results[] = [
                    'first' => $first,
                    'second' => $second,
                    'total_price_minor' => $this->minAdultPrice($first) + $this->minAdultPrice($second),
                    'connection_minutes' => $connectionMinutes,
                ];
            }
        }

        // Tri : prix croissant, puis correspondance la plus courte.
        usort($results, fn (array $a, array $b): int => $a['total_price_minor'] <=> $b['total_price_minor']
            ?: $a['connection_minutes'] <=> $b['connection_minutes']);

        return array_slice($results, 0, 50);
    }

    /**
     * Trajets publiés du jour : départ depuis `cityId` (isOrigin) ou arrivée
     * à `cityId` (destination).
     */
    private function legs(int $cityId, string $date, bool $isOrigin): Collection
    {
        /** @var Collection<int, TravelTrip> $trips */
        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->where('status', 'published')
            ->whereDate('departure_date', $date)
            ->whereHas('route', fn (Builder $query) => $isOrigin
                ? $query->where('origin_city_id', $cityId)
                : $query->where('destination_city_id', $cityId))
            ->get();

        return $trips;
    }

    private function datetime(TravelTrip $trip, bool $useArrival): CarbonImmutable
    {
        $date = $useArrival ? $trip->arrival_date : $trip->departure_date;
        $time = $useArrival ? $trip->arrival_time : $trip->departure_time;

        return CarbonImmutable::parse($date->toDateString().' '.$time);
    }

    private function minAdultPrice(TravelTrip $trip): int
    {
        $min = $trip->prices
            ->map(fn (TravelTripPrice $price): int => (int) $price->adult_price_minor)
            ->min();

        return $min ?? 0;
    }
}
