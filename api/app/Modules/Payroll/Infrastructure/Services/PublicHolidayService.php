<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Issue #1811 — Jours fériés par pays et jours ouvrés réels.
 *
 * Source de vérité : table publique `public_holidays` (national + entreprise).
 * La requête est cachée (Redis, TTL 24 h) pour ne pas pénaliser les clôtures
 * de paie massives.
 *
 * Règles :
 *  - `getHolidays(country, year, companyId)` → fériés entreprise (override)
 *    ∪ fériés nationaux (lecture seule par tous les tenants) ;
 *  - `workingDaysBetween(...)` → jours calendaires de la période, moins les
 *    jours de repos hebdomadaire du pays (`weeklyRestDays()`, ex. DZ =
 *    vendredi/samedi), moins les fériés, arrondi à 2 décimales ;
 *  - fallback : si AUCUN férié n'est configuré pour le pays/année, retourne
 *    le calendrier hors jours de repos (≈ 22 jours ouvrés mensuels —
 *    comportement historique de `STANDARD_WORKING_DAYS` préservé pour les
 *    pays non encore alimentés).
 */
class PublicHolidayService
{
    private const CACHE_TTL_SECONDS = 86_400; // 24 h

    public function __construct(private readonly CacheRepository $cache)
    {
    }

    /**
     * Fériés pour un pays + année, avec override entreprise.
     *
     * @return array<int, array{date: string, name: string, holiday_type: string, company_id: int|null}>
     */
    public function getHolidays(string $countryCode, int $year, ?int $companyId = null): array
    {
        $cacheKey = sprintf('public-holidays:%s:%d:%d', strtoupper($countryCode), $year, (int) $companyId);

        return $this->cache->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($countryCode, $year, $companyId): array {
            $query = PublicHoliday::query()
                ->where('country_code', strtoupper($countryCode))
                ->where('year', $year);

            // company_id NULL = férié national ; company_id = tenant → override.
            $query->where(function ($q) use ($companyId): void {
                $q->whereNull('company_id');
                if ($companyId !== null) {
                    $q->orWhere('company_id', $companyId);
                }
            });

            return $query
                ->orderBy('date')
                ->get()
                ->map(fn (PublicHoliday $h): array => [
                    'date' => $h->date->toDateString(),
                    'name' => $h->name,
                    'holiday_type' => $h->holiday_type,
                    'company_id' => $h->company_id,
                ])
                ->all();
        });
    }

    /**
     * Nombre de jours ouvrés réels entre deux dates (inclusives), en excluant
     * les jours de repos hebdomadaire et les jours fériés du pays (avec
     * override entreprise).
     *
     * @param  Carbon  $start  début inclus
     * @param  Carbon  $end    fin inclusive
     * @param  array<int, array{date: string, name: string, holiday_type: string, company_id: int|null}>|null  $holidays  liste préchargée (optionnel)
     * @param  array<int, int>  $restDays  jours de repos ISO (1=lundi..7=dimanche) ; défaut samedi+dimanche
     */
    public function workingDaysBetween(
        Carbon $start,
        Carbon $end,
        string $countryCode,
        ?array $holidays = null,
        ?int $companyId = null,
        array $restDays = [6, 7],
    ): float {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        // Récupération des fériés (préchargés ou via le cache).
        if ($holidays === null) {
            $holidays = $this->getHolidays($countryCode, (int) $start->year, $companyId);
        }

        // Fallback : aucun férié configuré → calendrier hors jours de repos
        // (comportement historique ≈ 22 jours ouvrés mensuels).
        if ($holidays === []) {
            return $this->workingDaysWithoutHolidays($start, $end, $restDays);
        }

        $holidayDates = [];
        foreach ($holidays as $holiday) {
            /** @var string $date */
            $date = $holiday['date'];
            $holidayDates[$date] = true;
        }

        $workingDays = 0.0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $isoDay = (int) $cursor->dayOfWeekIso;
            if (! in_array($isoDay, $restDays, true)
                && ! isset($holidayDates[$cursor->toDateString()])) {
                $workingDays += 1.0;
            }
            $cursor->addDay();
        }

        return round($workingDays, 2);
    }

    /**
     * Jours ouvrés sans connaissance des fériés : calendrier moins les jours
     * de repos hebdomadaire.
     *
     * @param  array<int, int>  $restDays
     */
    private function workingDaysWithoutHolidays(Carbon $start, Carbon $end, array $restDays): float
    {
        $workingDays = 0.0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (! in_array((int) $cursor->dayOfWeekIso, $restDays, true)) {
                $workingDays += 1.0;
            }
            $cursor->addDay();
        }

        return round($workingDays, 2);
    }

    /**
     * Invalide le cache d'un pays/année (après CRUD admin).
     */
    public function forget(string $countryCode, int $year, ?int $companyId = null): void
    {
        $cacheKey = sprintf('public-holidays:%s:%d:%d', strtoupper($countryCode), $year, (int) $companyId);
        $this->cache->forget($cacheKey);
    }
}
