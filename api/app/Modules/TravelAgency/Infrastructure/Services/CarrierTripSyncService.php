<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-807 (#6086) — Synchronisation des trajets transporteurs
 * (API entrante).
 *
 * Upsert idempotent d'un trajet par sa clé externe (`external_id`,
 * unique tenant/transporteur) : rejouer la même charge ne duplique jamais
 * le trajet (critère d'acceptation). La route est résolue par
 * `route.external_id` (upsert) ou `route_id` existant ; les tarifs sont
 * synchronisés par classe (upsert). Bornes : total_seats ≤ 200, tarifs
 * positifs en unités mineures.
 *
 * @phpstan-type SyncPriceInput array{
 *     class_id?: int|null,
 *     class_code?: string|null,
 *     adult_price_minor: int,
 *     child_price_minor?: int|null,
 *     currency?: string|null
 * }
 */
final class CarrierTripSyncService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertTrip(string $companyId, int $carrierId, array $payload): TravelTrip
    {
        $externalId = (string) ($payload['external_id'] ?? '');
        if ($externalId === '') {
            abort(422, 'external_id obligatoire pour la synchronisation.');
        }

        $totalSeats = (int) ($payload['total_seats'] ?? 0);
        if ($totalSeats < 1 || $totalSeats > 200) {
            abort(422, 'total_seats doit être compris entre 1 et 200.');
        }

        return DB::transaction(function () use ($companyId, $carrierId, $payload, $externalId, $totalSeats): TravelTrip {
            $route = $this->resolveRoute($companyId, $carrierId, $payload);

            /** @var TravelTrip $trip */
            $trip = TravelTrip::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'carrier_id' => $carrierId,
                    'external_id' => $externalId,
                ],
                [
                    'code' => (string) ($payload['code'] ?? 'EXT-'.$externalId),
                    'route_id' => $route->id,
                    'carrier_id' => $carrierId,
                    'departure_date' => (string) ($payload['departure_date'] ?? ''),
                    'departure_time' => (string) ($payload['departure_time'] ?? '00:00'),
                    'arrival_date' => (string) ($payload['arrival_date'] ?? $payload['departure_date'] ?? ''),
                    'arrival_time' => (string) ($payload['arrival_time'] ?? '00:00'),
                    'means_of_transport' => (string) ($payload['means_of_transport'] ?? 'bus'),
                    'total_seats' => $totalSeats,
                    'status' => (string) ($payload['status'] ?? 'scheduled'),
                    'created_by_user_id' => null,
                ],
            );

            $this->syncPrices($companyId, $trip, $payload['prices'] ?? []);

            return $trip;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveRoute(string $companyId, int $carrierId, array $payload): TravelRoute
    {
        $routeExternalId = isset($payload['route']['external_id'])
            ? (string) $payload['route']['external_id']
            : null;

        if ($routeExternalId !== null && $routeExternalId !== '') {
            /** @var TravelRoute $route */
            $route = TravelRoute::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'carrier_id' => $carrierId,
                    'external_id' => $routeExternalId,
                ],
                [
                    'code' => (string) ($payload['route']['code'] ?? 'EXT-R-'.$routeExternalId),
                    'origin_city_id' => (int) ($payload['route']['origin_city_id'] ?? abort(422, 'route.origin_city_id obligatoire.')),
                    'destination_city_id' => (int) ($payload['route']['destination_city_id'] ?? abort(422, 'route.destination_city_id obligatoire.')),
                    'status' => (string) ($payload['route']['status'] ?? 'active'),
                ],
            );

            return $route;
        }

        $routeId = (int) ($payload['route_id'] ?? 0);

        /** @var TravelRoute $route */
        $route = TravelRoute::query()
            ->where('company_id', $companyId)
            ->whereKey($routeId)
            ->firstOrFail();

        return $route;
    }

    /**
     * @param  array<int, SyncPriceInput>  $prices
     */
    private function syncPrices(string $companyId, TravelTrip $trip, array $prices): void
    {
        foreach ($prices as $price) {
            $classId = $this->resolveClassId($companyId, $price);

            $adult = (int) $price['adult_price_minor'];
            if ($adult < 0) {
                abort(422, 'Tarif adulte invalide (unités mineures).');
            }

            TravelTripPrice::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'trip_id' => $trip->id,
                    'class_id' => $classId,
                ],
                [
                    'adult_price_minor' => $adult,
                    'child_price_minor' => isset($price['child_price_minor'])
                        ? (int) $price['child_price_minor']
                        : null,
                    'currency' => (string) ($price['currency'] ?? 'XAF'),
                ],
            );
        }
    }

    /**
     * @param  SyncPriceInput  $price
     */
    private function resolveClassId(string $companyId, array $price): int
    {
        $classId = isset($price['class_id']) ? (int) $price['class_id'] : null;
        $classCode = isset($price['class_code']) ? (string) $price['class_code'] : null;

        if ($classId !== null) {
            $exists = TravelClass::query()
                ->where('company_id', $companyId)
                ->whereKey($classId)
                ->exists();

            if ($exists) {
                return $classId;
            }
        }

        if ($classCode !== null && $classCode !== '') {
            /** @var TravelClass $class */
            $class = TravelClass::query()
                ->where('company_id', $companyId)
                ->where('code', $classCode)
                ->firstOrFail();

            return $class->id;
        }

        abort(422, 'class_id ou class_code obligatoire pour chaque tarif.');
    }
}
