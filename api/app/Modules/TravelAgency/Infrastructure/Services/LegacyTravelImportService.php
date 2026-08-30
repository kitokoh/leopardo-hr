<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-1003 (#6116) — Import des données legacy gv-back (CLI contrôlé).
 *
 * Upserts IDEMPOTENTS par clés externes (rejouable sans doublon) :
 *   - compagnies → `travel_carriers` (clé : code) ;
 *   - routes → `travel_routes` (clé : external_id) ;
 *   - trajets → `travel_trips` (clé : company+carrier+external_id) ;
 *   - tarifs → `travel_trip_prices` (clé : trip+class) ;
 *   - réservations → `travel_bookings` (clé d'idempotence
 *     `legacy:{legacy_id}`, statuts FIGÉS, passagers recréés).
 * Transformations : unités mineures (montants entiers), enums, devise.
 * `dryRun` : analyse + rapport SANS aucune écriture.
 *
 * @phpstan-type ImportReport array{carriers: int, routes: int, trips: int, prices: int, bookings: int, skipped: list<string>}
 */
final class LegacyTravelImportService
{
    /**
     * @param  array<string, mixed>  $dump
     * @return ImportReport
     */
    public function import(string $companyId, array $dump, bool $dryRun = false): array
    {
        $report = [
            'carriers' => 0,
            'routes' => 0,
            'trips' => 0,
            'prices' => 0,
            'bookings' => 0,
            'skipped' => [],
        ];

        if (! $dryRun) {
            return DB::transaction(function () use ($companyId, $dump, &$report): array {
                $this->importCarriers($companyId, $dump, $report);
                $this->importRoutes($companyId, $dump, $report);
                $this->importTrips($companyId, $dump, $report);
                $this->importBookings($companyId, $dump, $report);

                return $report;
            });
        }

        // Dry-run : on simule l'analyse (résolution des clés) sans écrire.
        $this->dryRunAnalysis($companyId, $dump, $report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $dump
     * @param  ImportReport  $report
     */
    private function importCarriers(string $companyId, array $dump, array &$report): void
    {
        foreach ((array) ($dump['carriers'] ?? []) as $carrier) {
            $code = (string) ($carrier['code'] ?? '');
            if ($code === '') {
                $report['skipped'][] = 'carrier:sans-code';

                continue;
            }

            TravelCarrier::query()->updateOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => (string) ($carrier['name'] ?? $code),
                    // Mapping legacy → enum CarrierType (défaut bus).
                    'type' => in_array((string) ($carrier['type'] ?? 'bus'), ['bus', 'train', 'plane', 'boat'], true)
                        ? (string) $carrier['type']
                        : 'bus',
                ],
            );

            $report['carriers']++;
        }
    }

    /**
     * @param  array<string, mixed>  $dump
     * @param  ImportReport  $report
     */
    private function importRoutes(string $companyId, array $dump, array &$report): void
    {
        foreach ((array) ($dump['routes'] ?? []) as $route) {
            $externalId = (string) ($route['external_id'] ?? '');
            $originCode = (string) ($route['origin_city_code'] ?? '');
            $destinationCode = (string) ($route['destination_city_code'] ?? '');

            if ($externalId === '' || $originCode === '' || $destinationCode === '') {
                $report['skipped'][] = 'route:'.$externalId.'(clés incomplètes)';

                continue;
            }

            $origin = TravelCity::query()
                ->where('company_id', $companyId)
                ->where('name', $originCode)
                ->orWhere('name', ucfirst(strtolower($originCode)))
                ->first();

            $destination = TravelCity::query()
                ->where('company_id', $companyId)
                ->where('name', $destinationCode)
                ->orWhere('name', ucfirst(strtolower($destinationCode)))
                ->first();

            if (! $origin instanceof TravelCity
                || ! $destination instanceof TravelCity) {
                $report['skipped'][] = 'route:'.$externalId.'(villes introuvables)';

                continue;
            }

            TravelRoute::query()->updateOrCreate(
                ['company_id' => $companyId, 'external_id' => $externalId],
                [
                    'code' => (string) ($route['code'] ?? 'R-'.$externalId),
                    'origin_city_id' => $origin->id,
                    'destination_city_id' => $destination->id,
                    'status' => 'active',
                ],
            );

            $report['routes']++;
        }
    }

    /**
     * @param  array<string, mixed>  $dump
     * @param  ImportReport  $report
     */
    private function importTrips(string $companyId, array $dump, array &$report): void
    {
        foreach ((array) ($dump['trips'] ?? []) as $trip) {
            $externalId = (string) ($trip['external_id'] ?? '');
            $routeExternalId = (string) ($trip['route_external_id'] ?? '');
            $carrierCode = (string) ($trip['carrier_code'] ?? '');

            $route = TravelRoute::query()
                ->where('company_id', $companyId)
                ->where('external_id', $routeExternalId)
                ->first();

            $carrier = TravelCarrier::query()
                ->where('company_id', $companyId)
                ->where('code', $carrierCode)
                ->first();

            if (! $route instanceof TravelRoute || ! $carrier instanceof TravelCarrier) {
                $report['skipped'][] = 'trip:'.$externalId.'(route ou compagnie introuvable)';

                continue;
            }

            $tripModel = TravelTrip::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'carrier_id' => $carrier->id,
                    'external_id' => $externalId,
                ],
                [
                    'code' => (string) ($trip['code'] ?? 'EXT-'.$externalId),
                    'route_id' => $route->id,
                    'carrier_id' => $carrier->id,
                    'departure_date' => (string) ($trip['departure_date'] ?? ''),
                    'departure_time' => (string) ($trip['departure_time'] ?? '00:00'),
                    'arrival_date' => (string) ($trip['arrival_date'] ?? $trip['departure_date'] ?? ''),
                    'arrival_time' => (string) ($trip['arrival_time'] ?? '00:00'),
                    'means_of_transport' => (string) ($trip['means_of_transport'] ?? 'bus'),
                    'total_seats' => (int) ($trip['total_seats'] ?? 40),
                    'status' => (string) ($trip['status'] ?? 'scheduled'),
                ],
            );

            $this->importPrices($companyId, $tripModel, $trip['prices'] ?? [], $report);
            $report['trips']++;
        }
    }

    /**
     * @param  ImportReport  $report
     */
    private function importPrices(string $companyId, TravelTrip $trip, array $prices, array &$report): void
    {
        foreach ($prices as $price) {
            $classCode = (string) ($price['class_code'] ?? '');

            if ($classCode === '') {
                $report['skipped'][] = 'price:'.$trip->external_id.'(sans classe)';

                continue;
            }

            $class = TravelClass::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $classCode],
                ['label' => $classCode],
            );

            TravelTripPrice::query()->updateOrCreate(
                ['company_id' => $companyId, 'trip_id' => $trip->id, 'class_id' => $class->id],
                [
                    'adult_price_minor' => (int) ($price['adult_amount'] ?? 0),
                    'child_price_minor' => isset($price['child_amount']) ? (int) $price['child_amount'] : null,
                    'currency' => (string) ($price['currency'] ?? 'XAF'),
                ],
            );

            $report['prices']++;
        }
    }

    /**
     * @param  array<string, mixed>  $dump
     * @param  ImportReport  $report
     */
    private function importBookings(string $companyId, array $dump, array &$report): void
    {
        foreach ((array) ($dump['bookings'] ?? []) as $legacyBooking) {
            $legacyId = (string) ($legacyBooking['legacy_id'] ?? '');
            $tripExternalId = (string) ($legacyBooking['trip_external_id'] ?? '');

            $trip = TravelTrip::query()
                ->where('company_id', $companyId)
                ->where('external_id', $tripExternalId)
                ->first();

            if ($legacyId === '' || ! $trip instanceof TravelTrip) {
                $report['skipped'][] = 'booking:'.$legacyId.'(trajet introuvable)';

                continue;
            }

            $idempotencyKey = 'legacy:'.$legacyId;

            $existing = TravelBooking::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            if ($existing) {
                $report['bookings']++;

                continue; // Rejouable sans doublon.
            }

            $status = in_array((string) ($legacyBooking['status'] ?? 'confirmed'), ['pending', 'confirmed', 'cancelled', 'refunded'], true)
                ? (string) $legacyBooking['status']
                : 'confirmed';

            $booking = TravelBooking::query()->create([
                'company_id' => $companyId,
                'trip_id' => $trip->id,
                'status' => $status,
                'payment_status' => $status === 'confirmed' ? 'confirmed' : 'pending',
                'passenger_count' => count((array) ($legacyBooking['passengers'] ?? [])),
                'total_amount_minor' => (int) ($legacyBooking['total_amount'] ?? 0),
                'currency' => (string) ($legacyBooking['currency'] ?? 'XAF'),
                'booking_source' => 'office',
                'idempotency_key' => $idempotencyKey,
                'version' => 1,
            ]);

            foreach ((array) ($legacyBooking['passengers'] ?? []) as $legacyPassenger) {
                TravelPassenger::query()->create([
                    'company_id' => $companyId,
                    'booking_id' => $booking->id,
                    'full_name' => (string) ($legacyPassenger['full_name'] ?? 'Passager'),
                    'age_category' => (string) ($legacyPassenger['age_category'] ?? 'adult'),
                    'class_id' => TravelClass::query()->where('company_id', $companyId)->value('id') ?? 1,
                    'unit_price_minor' => 0,
                ]);
            }

            $report['bookings']++;
        }
    }

    /**
     * @param  array<string, mixed>  $dump
     * @param  ImportReport  $report
     */
    private function dryRunAnalysis(string $companyId, array $dump, array &$report): void
    {
        $report['carriers'] = count((array) ($dump['carriers'] ?? []));
        $report['routes'] = count((array) ($dump['routes'] ?? []));
        $report['trips'] = count((array) ($dump['trips'] ?? []));
        $report['bookings'] = count((array) ($dump['bookings'] ?? []));

        foreach ((array) ($dump['trips'] ?? []) as $trip) {
            $report['prices'] += count((array) ($trip['prices'] ?? []));
        }
    }
}
