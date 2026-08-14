<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Issue #1812 — Calendrier islamique dynamique.
 *
 * Source de vérité : table publique `islamic_calendar` (schéma `public`).
 * Les dates y sont saisies/confirmées par un admin plateforme pour chaque
 * année, sans changement de code. Le mapping pays → fêtes applicables vit
 * dans `config/islamic_holidays_map.php`.
 *
 * - `getHolidaysForCountry()` → entrées de la table filtrées par pays (clés
 *   autorisées + durée locale) ;
 * - `resolveForPayroll()` → même résultat, mais aplati en une liste de dates
 *   consécutives (la durée d'un pays peut être 2 jours, ex. Aïd au Cameroun)
 *   au format attendu par `PublicHolidayService::getHolidays()`.
 *
 * Le cache est invalidé par les écritures admin (via `forget()`).
 */
class IslamicCalendarService
{
    private const CACHE_TTL_SECONDS = 86_400; // 24 h

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Fêtes islamiques applicables à un pays pour une année.
     *
     * @return array<int, array{
     *   holiday_key: string,
     *   name: string,
     *   date: string,
     *   duration_days: int,
     *   confirmed: bool,
     *   source: string,
     *   countries: list<string>,
     * }>
     */
    public function getHolidaysForCountry(string $countryCode, int $year): array
    {
        $countryCode = strtoupper($countryCode);
        $mapping = $this->countryMapping($countryCode);

        if ($mapping === []) {
            return [];
        }

        $cacheKey = sprintf('islamic-calendar:%s:%d', $countryCode, $year);

        return $this->cache->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($countryCode, $year, $mapping): array {
            return IslamicCalendar::query()
                ->where('year', $year)
                ->whereIn('holiday_key', array_keys($mapping))
                ->orderBy('gregorian_date')
                ->get()
                ->map(function (IslamicCalendar $holiday) use ($countryCode, $mapping): array {
                    $config = $mapping[$holiday->holiday_key];

                    return [
                        'holiday_key' => $holiday->holiday_key,
                        'name' => (string) ($config['name'] ?? $holiday->holiday_key),
                        'date' => $holiday->gregorian_date->toDateString(),
                        // La durée du pays prime sur la durée par défaut de la table.
                        'duration_days' => max(1, (int) ($config['duration'] ?? $holiday->duration_days)),
                        'confirmed' => $holiday->confirmed,
                        'source' => $holiday->source,
                        'countries' => [$countryCode],
                    ];
                })
                ->all();
        });
    }

    /**
     * Fêtes islamiques d'un pays aplaties en jours chômés consécutifs, au
     * format attendu par `PublicHolidayService::getHolidays()`.
     *
     * Issue #1930 [P1] — seules les dates CONFIRMÉES (`confirmed = true`,
     * validées par un admin plateforme) alimentent le calcul des jours ouvrés
     * et la paie. Les dates approximatives du seeder (`source = 'computed'`,
     * `confirmed = false`) sont exclues : les intégrer reviendrait à payer sur
     * un calendrier non fiable. `getHolidaysForCountry()` (usage admin) garde
     * les dates non confirmées pour permettre leur validation.
     *
     * @return array<int, array{date: string, name: string, holiday_type: string, company_id: null}>
     */
    public function resolveForPayroll(string $countryCode, int $year, ?string $companyId = null): array
    {
        $holidays = array_filter(
            $this->getHolidaysForCountry($countryCode, $year),
            static fn (array $holiday): bool => (bool) $holiday['confirmed'],
        );

        $resolved = [];
        foreach ($holidays as $holiday) {
            $start = Carbon::parse($holiday['date'])->startOfDay();
            for ($i = 0; $i < $holiday['duration_days']; $i++) {
                $resolved[] = [
                    'date' => $start->copy()->addDays($i)->toDateString(),
                    'name' => $holiday['name'],
                    'holiday_type' => 'islamic',
                    'company_id' => null,
                ];
            }
        }

        return $resolved;
    }

    /**
     * Confirme (ou met à jour) une date islamique — appels admin plateforme.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $holidayKey, int $year, array $data): IslamicCalendar
    {
        /** @var IslamicCalendar $holiday */
        $holiday = IslamicCalendar::query()->firstOrNew(
            ['holiday_key' => $holidayKey, 'year' => $year],
        );

        $holiday->fill($data);
        $holiday->save();

        $this->forgetAll($year);

        return $holiday->fresh() ?? $holiday;
    }

    /**
     * Marque toutes les dates d'une année comme confirmées (source = manual).
     *
     * @return int nombre de dates confirmées
     */
    public function confirmYear(int $year, int $confirmedBy): int
    {
        $count = IslamicCalendar::query()
            ->where('year', $year)
            ->where('confirmed', false)
            ->update([
                'confirmed' => true,
                'source' => 'manual',
                'confirmed_by' => $confirmedBy,
                'updated_at' => now(),
            ]);

        $this->forgetAll($year);

        return $count;
    }

    /**
     * Fêtes islamiques non confirmées d'une année (rappel admin / banner UI).
     *
     * @return array<int, array{holiday_key: string, year: int, gregorian_date: string}>
     */
    public function unconfirmedForYear(int $year): array
    {
        return IslamicCalendar::query()
            ->where('year', $year)
            ->where('confirmed', false)
            ->orderBy('gregorian_date')
            ->get()
            ->map(fn (IslamicCalendar $h): array => [
                'holiday_key' => $h->holiday_key,
                'year' => (int) $h->year,
                'gregorian_date' => $h->gregorian_date->toDateString(),
            ])
            ->all();
    }

    /**
     * Invalide les caches d'une année pour tous les pays du mapping.
     */
    public function forgetAll(int $year): void
    {
        foreach (array_keys((array) config('islamic_holidays_map.countries', [])) as $countryCode) {
            $this->cache->forget(sprintf('islamic-calendar:%s:%d', (string) $countryCode, $year));
        }
    }

    /**
     * @return array<string, array{duration?: int, name?: string}>
     */
    private function countryMapping(string $countryCode): array
    {
        /** @var array<string, array{duration?: int, name?: string}> $mapping */
        $mapping = (array) config(sprintf('islamic_holidays_map.countries.%s', $countryCode), []);

        return $mapping;
    }
}
