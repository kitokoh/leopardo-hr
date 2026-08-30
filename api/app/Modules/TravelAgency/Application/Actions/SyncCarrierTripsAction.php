<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Enums\MeansOfTransport;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-807 (#6086) — Synchronisation entrante des trajets transporteurs.
 *
 * Upsert idempotent par clé externe : une route ou un trajet portant le même
 * `external_ref` (tenant + transporteur) est mis à jour, jamais dupliqué.
 * Les éléments invalides sont collectés dans `errors` sans bloquer le lot
 * (fail-per-item, bornes de taille appliquées côté requête).
 *
 * @phpstan-type SyncRouteInput array{
 *     external_ref: string,
 *     code?: string|null,
 *     origin_city_id?: int|null,
 *     destination_city_id?: int|null,
 *     distance_km?: int|null,
 *     duration_min?: int|null,
 *     status?: string|null
 * }
 * @phpstan-type SyncPriceInput array{
 *     class_code: string,
 *     adult_price_minor: int,
 *     child_price_minor?: int|null,
 *     currency: string
 * }
 * @phpstan-type SyncTripInput array{
 *     external_ref: string,
 *     route_external_ref: string,
 *     code?: string|null,
 *     departure_date: string,
 *     departure_time: string,
 *     arrival_date: string,
 *     arrival_time: string,
 *     means_of_transport?: string|null,
 *     total_seats?: int|null,
 *     status?: string|null,
 *     prices?: list<SyncPriceInput>|null
 * }
 */
final class SyncCarrierTripsAction
{
    /**
     * @param  list<SyncRouteInput>  $routes
     * @param  list<SyncTripInput>  $trips
     * @return array{routes_created: int, routes_updated: int, trips_created: int, trips_updated: int, errors: list<string>}
     */
    public function execute(Company $company, TravelCarrier $carrier, array $routes, array $trips): array
    {
        $errors = [];

        $result = DB::transaction(function () use ($company, $carrier, $routes, $trips, &$errors): array {
            $routesCreated = 0;
            $routesUpdated = 0;
            $tripsCreated = 0;
            $tripsUpdated = 0;

            $routeByExternalRef = [];

            foreach ($routes as $routeData) {
                try {
                    $route = $this->upsertRoute($company, $carrier, $routeData);
                    $routeByExternalRef[$routeData['external_ref']] = $route;
                    if ($route->wasRecentlyCreated) {
                        $routesCreated++;
                    } else {
                        $routesUpdated++;
                    }
                } catch (\Throwable $exception) {
                    $errors[] = 'route '.$routeData['external_ref'].': '.$exception->getMessage();
                }
            }

            foreach ($trips as $tripData) {
                try {
                    $route = $routeByExternalRef[$tripData['route_external_ref']] ?? $this->findRouteByExternalRef($company, $carrier, $tripData['route_external_ref']);

                    if (! $route instanceof TravelRoute) {
                        $errors[] = 'trip '.$tripData['external_ref'].': route inconnue '.$tripData['route_external_ref'];

                        continue;
                    }

                    $trip = $this->upsertTrip($company, $carrier, $tripData, $route);
                    if ($trip->wasRecentlyCreated) {
                        $tripsCreated++;
                    } else {
                        $tripsUpdated++;
                    }
                } catch (\Throwable $exception) {
                    $errors[] = 'trip '.$tripData['external_ref'].': '.$exception->getMessage();
                }
            }

            return [
                'routes_created' => $routesCreated,
                'routes_updated' => $routesUpdated,
                'trips_created' => $tripsCreated,
                'trips_updated' => $tripsUpdated,
            ];
        });

        $result['errors'] = $errors;

        return $result;
    }

    /**
     * @param  SyncRouteInput  $data
     */
    private function upsertRoute(Company $company, TravelCarrier $carrier, array $data): TravelRoute
    {
        $route = $this->findRouteByExternalRef($company, $carrier, $data['external_ref']);

        $attributes = [
            'code' => $data['code'] ?? $data['external_ref'],
            'origin_city_id' => $data['origin_city_id'] ?? null,
            'destination_city_id' => $data['destination_city_id'] ?? null,
            'distance_km' => $data['distance_km'] ?? null,
            'duration_min' => $data['duration_min'] ?? null,
            'status' => $data['status'] ?? TravelRecordStatus::ACTIVE->value,
            'external_ref' => $data['external_ref'],
            'external_carrier_code' => $carrier->code,
        ];

        if ($route instanceof TravelRoute) {
            $route->update($attributes);

            return $route;
        }

        return TravelRoute::query()->create($attributes);
    }

    /**
     * @param  SyncTripInput  $data
     */
    private function upsertTrip(Company $company, TravelCarrier $carrier, array $data, TravelRoute $route): TravelTrip
    {
        $trip = $this->findTripByExternalRef($company, $carrier, $data['external_ref']);

        $attributes = [
            'code' => $data['code'] ?? $data['external_ref'],
            'route_id' => $route->id,
            'departure_date' => $data['departure_date'],
            'departure_time' => $data['departure_time'],
            'arrival_date' => $data['arrival_date'],
            'arrival_time' => $data['arrival_time'],
            'means_of_transport' => $data['means_of_transport'] ?? MeansOfTransport::BUS->value,
            'total_seats' => $data['total_seats'] ?? 40,
            'status' => $data['status'] ?? TripStatus::SCHEDULED->value,
            'external_ref' => $data['external_ref'],
            'external_carrier_code' => $carrier->code,
        ];

        if ($trip instanceof TravelTrip) {
            $trip->update($attributes);
        } else {
            $trip = TravelTrip::query()->create($attributes);
            app(GenerateTripSeatsAction::class)->execute($trip);
        }

        foreach ($data['prices'] ?? [] as $priceData) {
            $this->upsertPrice($company, $trip, $priceData);
        }

        return $trip;
    }

    /**
     * @param  SyncPriceInput  $data
     */
    private function upsertPrice(Company $company, TravelTrip $trip, array $data): void
    {
        /** @var TravelClass|null $class */
        $class = TravelClass::query()->where('code', $data['class_code'])->first();

        if (! $class instanceof TravelClass) {
            throw new \RuntimeException('classe inconnue '.$data['class_code']);
        }

        TravelTripPrice::query()->updateOrCreate(
            ['company_id' => $company->id, 'trip_id' => $trip->id, 'class_id' => $class->id],
            [
                'adult_price_minor' => $data['adult_price_minor'],
                'child_price_minor' => $data['child_price_minor'] ?? null,
                'currency' => $data['currency'],
            ],
        );
    }

    private function findRouteByExternalRef(Company $company, TravelCarrier $carrier, string $externalRef): ?TravelRoute
    {
        /** @var TravelRoute|null $route */
        $route = TravelRoute::query()
            ->where('company_id', $company->id)
            ->where('external_ref', $externalRef)
            ->where('external_carrier_code', $carrier->code)
            ->first();

        return $route;
    }

    private function findTripByExternalRef(Company $company, TravelCarrier $carrier, string $externalRef): ?TravelTrip
    {
        /** @var TravelTrip|null $trip */
        $trip = TravelTrip::query()
            ->where('company_id', $company->id)
            ->where('external_ref', $externalRef)
            ->where('external_carrier_code', $carrier->code)
            ->first();

        return $trip;
    }
}
