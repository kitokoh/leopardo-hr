<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TRAVEL-1003 (#6116) — Import des données legacy gv-back (CLI contrôlé).
 *
 * Mapping documenté, transformations (statuts, minor units), idempotence
 * (updateOrCreate sur les clés uniques tenant-scoped), rapport complet.
 * Jamais de consentement accordé par l'import (RGPD : l'opt-in reste
 * explicite). Les inconnues (ville/compagnie/classe absentes) sont
 * SKIPpées et comptées dans le rapport — jamais d'écriture partielle
 * silencieuse.
 */
final class TravelLegacyImportService
{
    /**
     * @param  array<string, mixed>  $dump
     * @return array<string, mixed>
     */
    public function import(Company $company, array $dump, bool $dryRun = false): array
    {
        $report = [
            'routes' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'trips' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'prices' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'bookings' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'passengers' => ['created' => 0, 'skipped' => 0],
            'contacts' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        ];

        if ($dryRun) {
            return $this->dryRunReport($company, $dump);
        }

        DB::transaction(function () use ($company, $dump, &$report): void {
            $cityByKey = $this->cityIndex($company);

            foreach ($dump['routes'] ?? [] as $row) {
                $this->importRoute($company, $row, $cityByKey, $report['routes']);
            }

            foreach ($dump['trips'] ?? [] as $row) {
                $this->importTrip($company, $row, $report['trips'], $report['prices']);
            }

            foreach ($dump['bookings'] ?? [] as $row) {
                $this->importBooking($company, $row, $report['bookings'], $report['passengers']);
            }

            foreach ($dump['contacts'] ?? [] as $row) {
                $this->importContact($company, $row, $report['contacts']);
            }
        });

        return $report;
    }

    /**
     * @param  array<string, mixed>  $dump
     * @return array<string, mixed>
     */
    private function dryRunReport(Company $company, array $dump): array
    {
        $cityByKey = $this->cityIndex($company);

        return [
            'dry_run' => true,
            'routes' => ['total' => count($dump['routes'] ?? []), 'resolvable' => $this->countResolvableRoutes($dump['routes'] ?? [], $cityByKey)],
            'trips' => ['total' => count($dump['trips'] ?? [])],
            'bookings' => ['total' => count($dump['bookings'] ?? [])],
            'contacts' => ['total' => count($dump['contacts'] ?? [])],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     */
    private function countResolvableRoutes(array $routes, array $cityByKey): int
    {
        $count = 0;

        foreach ($routes as $route) {
            if (isset($cityByKey[strtolower((string) ($route['origin_city'] ?? ''))])
                && isset($cityByKey[strtolower((string) ($route['destination_city'] ?? ''))])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, int> clé = nom de ville normalisé → id
     */
    private function cityIndex(Company $company): array
    {
        $index = [];

        foreach (TravelCity::query()->where('company_id', $company->id)->get(['id', 'name']) as $city) {
            $index[strtolower(trim((string) $city->name))] = (int) $city->id;
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $counter
     * @param  array<string, int>  $cityByKey
     */
    private function importRoute(Company $company, array $row, array $cityByKey, array &$counter): void
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            $counter['skipped']++;

            return;
        }

        $originId = $cityByKey[strtolower(trim((string) ($row['origin_city'] ?? '')))] ?? null;
        $destinationId = $cityByKey[strtolower(trim((string) ($row['destination_city'] ?? '')))] ?? null;

        if ($originId === null || $destinationId === null) {
            $counter['skipped']++;

            return;
        }

        /** @var TravelRoute|null $route */
        $route = TravelRoute::query()->where('company_id', $company->id)->where('code', $code)->first();

        $values = [
            'company_id' => $company->id,
            'origin_city_id' => $originId,
            'destination_city_id' => $destinationId,
            'distance_km' => isset($row['distance_km']) ? (int) $row['distance_km'] : null,
            'duration_min' => isset($row['duration_min']) ? (int) $row['duration_min'] : null,
            'status' => $row['status'] ?? 'active',
        ];

        if ($route instanceof TravelRoute) {
            $route->forceFill($values)->save();
            $counter['updated']++;
        } else {
            TravelRoute::query()->create($values);
            $counter['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $counter
     * @param  array<string, mixed>  $priceCounter
     */
    private function importTrip(Company $company, array $row, array &$counter, array &$priceCounter): void
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            $counter['skipped']++;

            return;
        }

        $route = isset($row['route_code']) ? TravelRoute::query()
            ->where('company_id', $company->id)
            ->where('code', (string) $row['route_code'])
            ->first() : null;

        if ($route === null) {
            $counter['skipped']++;

            return;
        }

        $status = (string) ($row['status'] ?? 'draft');
        $status = in_array($status, ['draft', 'scheduled', 'published', 'cancelled'], true) ? $status : 'draft';

        /** @var TravelTrip|null $trip */
        $trip = TravelTrip::query()->where('company_id', $company->id)->where('code', $code)->first();

        $values = [
            'company_id' => $company->id,
            'route_id' => $route->id,
            'carrier_id' => null,
            'vehicle_id' => null,
            'departure_date' => $row['departure_date'] ?? now()->toDateString(),
            'departure_time' => $row['departure_time'] ?? '08:00',
            'arrival_date' => $row['arrival_date'] ?? null,
            'arrival_time' => $row['arrival_time'] ?? null,
            'means_of_transport' => $row['means_of_transport'] ?? 'bus',
            'total_seats' => (int) ($row['total_seats'] ?? 40),
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ];

        if ($trip instanceof TravelTrip) {
            $trip->forceFill($values)->save();
            $counter['updated']++;
        } else {
            $trip = TravelTrip::query()->create($values);
            $counter['created']++;
        }

        foreach ($row['prices'] ?? [] as $priceRow) {
            $this->importTripPrice($company, $trip, $priceRow, $priceCounter);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $counter
     */
    private function importTripPrice(Company $company, TravelTrip $trip, array $row, array &$counter): void
    {
        $class = isset($row['class_code']) ? TravelClass::query()
            ->where('company_id', $company->id)
            ->where('code', (string) $row['class_code'])
            ->first() : null;

        if ($class === null) {
            $counter['skipped']++;

            return;
        }

        $values = [
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'class_id' => $class->id,
            'adult_price_minor' => (int) ($row['adult_price_minor'] ?? 0),
            'child_price_minor' => isset($row['child_price_minor']) ? (int) $row['child_price_minor'] : null,
            'currency' => $row['currency'] ?? 'XAF',
        ];

        /** @var TravelTripPrice|null $price */
        $price = TravelTripPrice::query()
            ->where('company_id', $company->id)
            ->where('trip_id', $trip->id)
            ->where('class_id', $class->id)
            ->first();

        if ($price instanceof TravelTripPrice) {
            $price->forceFill($values)->save();
            $counter['updated']++;
        } else {
            TravelTripPrice::query()->create($values);
            $counter['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $counter
     * @param  array<string, mixed>  $passengerCounter
     */
    private function importBooking(Company $company, array $row, array &$counter, array &$passengerCounter): void
    {
        $reference = trim((string) ($row['reference'] ?? ''));

        if ($reference === '') {
            $counter['skipped']++;

            return;
        }

        $trip = isset($row['trip_code']) ? TravelTrip::query()
            ->where('company_id', $company->id)
            ->where('code', (string) $row['trip_code'])
            ->first() : null;

        if ($trip === null) {
            $counter['skipped']++;

            return;
        }

        $status = (string) ($row['status'] ?? 'pending');
        $status = in_array($status, ['pending', 'confirmed', 'cancelled', 'refunded', 'completed'], true) ? $status : 'pending';

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()->where('company_id', $company->id)->where('reference', $reference)->first();

        if ($booking instanceof TravelBooking) {
            // Statuts figés : une réservation importée ne change pas d'état.
            $counter['updated']++;

            return;
        }

        $passengers = $row['passengers'] ?? [];

        $booking = TravelBooking::query()->create([
            'company_id' => $company->id,
            'reference' => $reference,
            'trip_id' => $trip->id,
            'status' => $status,
            'passenger_count' => count($passengers),
            'total_amount_minor' => (int) ($row['total_amount_minor'] ?? 0),
            'currency' => $row['currency'] ?? 'XAF',
            'booking_source' => 'office',
            'payment_status' => in_array($status, ['confirmed', 'completed'], true) ? 'confirmed' : 'pending',
            'expires_at' => null,
            'idempotency_key' => 'legacy-'.$reference,
            'version' => 1,
        ]);

        $counter['created']++;

        foreach ($passengers as $passengerRow) {
            $ageCategory = (string) ($passengerRow['age_category'] ?? 'adult');
            $ageCategory = in_array($ageCategory, ['adult', 'child', 'infant'], true) ? $ageCategory : 'adult';

            TravelPassenger::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'full_name' => (string) ($passengerRow['full_name'] ?? 'Passager importé'),
                'age_category' => $ageCategory,
                'class_id' => null,
                'seat_number' => null,
                'unit_price_minor' => (int) ($passengerRow['unit_price_minor'] ?? 0),
            ]);
            $passengerCounter['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $counter
     */
    private function importContact(Company $company, array $row, array &$counter): void
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $counter['skipped']++;

            return;
        }

        /** @var TravelCustomerContact|null $contact */
        $contact = TravelCustomerContact::query()->where('company_id', $company->id)->where('email', $email)->first();

        $values = [
            'company_id' => $company->id,
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'email' => $email,
            'phone' => $row['phone'] ?? null,
            // RGPD : l'import n'accorde JAMAIS de consentement.
            'email_consent_given' => false,
            'sms_consent_given' => false,
            'whatsapp_consent_given' => false,
        ];

        if ($contact instanceof TravelCustomerContact) {
            $contact->forceFill($values)->save();
            $counter['updated']++;
        } else {
            TravelCustomerContact::query()->create($values);
            $counter['created']++;
        }
    }
}
