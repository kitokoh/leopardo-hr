<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Application\Services\ConnectionDelayResolver;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
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
 * départ(B) ≥ arrivée(A) + délai minimal de correspondance. Une correspondance
 * n'est valide QUE si cette contrainte est respectée (acceptance TRAVEL-809).
 * Prix total = somme des tarifs adultes minimum des deux trajets.
 *
 * M5 — le délai n'est plus la constante 45 : il est résolu par
 * `ConnectionDelayResolver` (surcharge de l'appelant → réglage de la gare de
 * correspondance → défaut du tenant).
 */
final class ConnectionSearchAction
{
    /**
     * Délai historique, conservé comme valeur de repli documentaire.
     *
     * @deprecated M5 — utiliser `ConnectionDelayResolver` ; la valeur effective
     *             vient de `config('travel.connections.default_min_minutes')`.
     */
    public const MIN_CONNECTION_MINUTES = 45;

    public function __construct(private readonly ConnectionDelayResolver $delays) {}

    /**
     * @return list<array{first: TravelTrip, second: TravelTrip, total_price_minor: int, connection_minutes: int}>
     */
    public function execute(int $originCityId, int $destinationCityId, string $date, ?int $minConnectionMinutes = null): array
    {
        $firstLegs = $this->legs($originCityId, $date, isOrigin: true);
        $secondLegs = $this->legs($destinationCityId, $date, isOrigin: false);

        if ($firstLegs->isEmpty() || $secondLegs->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($firstLegs as $first) {
            foreach ($secondLegs as $second) {
                $routeA = $first->route;
                $routeB = $second->route;

                if (! $routeA instanceof TravelRoute || ! $routeB instanceof TravelRoute) {
                    continue;
                }

                if ($routeA->destination_city_id !== $routeB->origin_city_id) {
                    continue;
                }

                $arrivalA = $this->datetime($first, useArrival: true);
                $departureB = $this->datetime($second, useArrival: false);

                $connectionMinutes = (int) $arrivalA->diffInMinutes($departureB, false);

                // M5 — le délai dépend de la gare de correspondance (ville
                // intermédiaire), pas d'une constante globale.
                $required = $this->delays->resolve($routeA->destination_city_id, $minConnectionMinutes);

                if ($connectionMinutes < $required) {
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
     *
     * @return Collection<int, TravelTrip>
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
