<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-1004 (#6117) — Import géographique legacy (pays/villes).
 *
 * Upsert IDEMPOTENT : pays par `iso2` (contrôle ISO 3166-1 alpha-2),
 * villes par (country_iso2, name) — doublons détectés, lignes invalides
 * rapportées (jamais silencieuses). Seed rejouable (critère d'acceptation).
 *
 * @phpstan-type GeoReport array{countries: int, cities: int, skipped: list<string>}
 */
final class LegacyGeoImportService
{
    /**
     * @param  array<string, mixed>  $dump
     * @return GeoReport
     */
    public function import(string $companyId, array $dump, bool $dryRun = false): array
    {
        $report = ['countries' => 0, 'cities' => 0, 'skipped' => []];

        if ($dryRun) {
            $report['countries'] = count((array) ($dump['countries'] ?? []));
            $report['cities'] = count((array) ($dump['cities'] ?? []));

            return $report;
        }

        return DB::transaction(function () use ($companyId, $dump, &$report): array {
            $countryIso2 = [];

            foreach ((array) ($dump['countries'] ?? []) as $country) {
                $iso2 = strtoupper((string) ($country['iso2'] ?? ''));

                if (! preg_match('/^[A-Z]{2}$/', $iso2)) {
                    $report['skipped'][] = 'country:'.$iso2.'(ISO invalide)';

                    continue;
                }

                $name = (string) ($country['name'] ?? '');
                if ($name === '') {
                    $report['skipped'][] = 'country:'.$iso2.'(sans nom)';

                    continue;
                }

                TravelCountry::query()->updateOrCreate(
                    ['company_id' => $companyId, 'iso2' => $iso2],
                    [
                        'iso3' => strtoupper((string) ($country['iso3'] ?? '')),
                        'name' => $name,
                        'phone_code' => isset($country['phone_code']) ? (int) $country['phone_code'] : null,
                        'status' => 'active',
                    ],
                );

                $countryIso2[$iso2] = true;
                $report['countries']++;
            }

            foreach ((array) ($dump['cities'] ?? []) as $city) {
                $countryIso2Value = strtoupper((string) ($city['country_iso2'] ?? ''));
                $name = trim((string) ($city['name'] ?? ''));

                if ($name === '' || ! isset($countryIso2[$countryIso2Value])) {
                    $report['skipped'][] = 'city:'.$name.'/'.$countryIso2Value.'(pays introuvable ou nom vide)';

                    continue;
                }

                // Doublon détecté (déjà présent) → rapporté, pas re-compté.
                $exists = TravelCity::query()
                    ->where('company_id', $companyId)
                    ->where('country_iso2', $countryIso2Value)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $report['skipped'][] = 'city:'.$name.'/'.$countryIso2Value.'(doublon)';

                    continue;
                }

                TravelCity::query()->create([
                    'company_id' => $companyId,
                    'country_iso2' => $countryIso2Value,
                    'name' => $name,
                    'region' => isset($city['region']) ? (string) $city['region'] : null,
                    'latitude' => isset($city['latitude']) ? (float) $city['latitude'] : null,
                    'longitude' => isset($city['longitude']) ? (float) $city['longitude'] : null,
                    'status' => 'active',
                ]);

                $report['cities']++;
            }

            return $report;
        });
    }
}
