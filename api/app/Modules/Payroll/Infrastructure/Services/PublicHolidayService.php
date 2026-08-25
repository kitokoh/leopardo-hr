<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
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

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ?IslamicCalendarService $islamicCalendarService = null,
    ) {}

    /**
     * Fériés pour un pays + année, avec override entreprise.
     * Les fêtes islamiques mobiles (issue #1812) sont fusionnées avec les
     * fériés fixes de la table — les dates de `islamic_calendar` enrichissent
     * le calendrier au runtime.
     *
     * @return array<int, array{date: string, name: string, holiday_type: string, company_id: string|null}>
     */
    public function getHolidays(string $countryCode, int $year, ?string $companyId = null): array
    {
        $cacheKey = sprintf('public-holidays:%s:%d:%s', strtoupper($countryCode), $year, (string) ($companyId ?? 'null'));

        return $this->cache->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($countryCode, $year, $companyId): array {
            $query = PublicHoliday::query()
                ->where('country_code', strtoupper($countryCode))
                ->where(function ($q) use ($year): void {
                    // #1936 : les fériés RÉCURRENTS (is_recurring, month_day)
                    // s'appliquent à TOUTES les années — pas seulement à
                    // l'année stockée (date = première occurrence). Le filtre
                    // year exact les rendait invisibles hors année de création.
                    $q->where('year', $year)
                        ->orWhere('is_recurring', true);
                });

            // company_id NULL = férié national ; company_id = tenant → override.
            $query->where(function ($q) use ($companyId): void {
                $q->whereNull('company_id');
                if ($companyId !== null) {
                    $q->orWhere('company_id', $companyId);
                }
            });

            $fixed = $query
                ->orderBy('date')
                ->get()
                ->map(fn (PublicHoliday $h): array => [
                    'date' => $this->effectiveDate($h, $year),
                    'name' => $h->name,
                    'holiday_type' => $h->holiday_type,
                    'company_id' => $h->company_id,
                ])
                ->all();

            // Fusion des fêtes islamiques mobiles (nationales, lecture seule
            // pour tous les tenants).
            $islamic = $this->islamicCalendarService?->resolveForPayroll(
                $countryCode,
                $year,
                $companyId,
            ) ?? [];

            return $this->mergeHolidays($fixed, $islamic);
        });
    }

    /**
     * #1936 — date effective d'un férié pour une année donnée : pour un férié
     * récurrent, l'année demandée remplace l'année stockée (la date stockée
     * est la première occurrence ; month_day porte mois-jour). Les lignes
     * récurrentes legacy sans month_day (créées avant que l'UI n'envoie le
     * champ) dérivent month_day de la date stockée — sans quoi le férié
     * n'est jamais appliqué hors de son année de création.
     */
    private function effectiveDate(PublicHoliday $holiday, int $year): string
    {
        if ($holiday->is_recurring) {
            return sprintf('%04d-%s', $year, $holiday->month_day ?? $holiday->date->format('m-d'));
        }

        return $holiday->date->toDateString();
    }

    /**
     * Fusionne deux listes de fériés (fixes + islamiques) en dédupliquant par
     * date (le férié fixe/entreprise prime sur l'islamique le cas échéant) et
     * en triant par date.
     *
     * @param  array<int, array{date: string, name: string, holiday_type: string, company_id: string|null}>  $fixed
     * @param  array<int, array{date: string, name: string, holiday_type: string, company_id: null}>  $islamic
     * @return array<int, array{date: string, name: string, holiday_type: string, company_id: string|null}>
     */
    private function mergeHolidays(array $fixed, array $islamic): array
    {
        $byDate = [];
        foreach ($fixed as $holiday) {
            $byDate[$holiday['date']] = $holiday;
        }
        foreach ($islamic as $holiday) {
            // L'islamique ne remplace jamais un férié déjà enregistré.
            $byDate[$holiday['date']] ??= $holiday;
        }

        $merged = array_values($byDate);
        usort($merged, fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));

        return $merged;
    }

    /**
     * Nombre de jours ouvrés réels entre deux dates (inclusives), en excluant
     * les jours de repos hebdomadaire et les jours fériés du pays (avec
     * override entreprise).
     *
     * @param  Carbon  $start  début inclus
     * @param  Carbon  $end  fin inclusive
     * @param  array<int, array{date: string, name: string, holiday_type: string, company_id: string|int|null}>|null  $holidays  liste préchargée (optionnel)
     * @param  array<int, int>  $restDays  jours de repos ISO (1=lundi..7=dimanche) ; défaut samedi+dimanche
     */
    public function workingDaysBetween(
        Carbon $start,
        Carbon $end,
        string $countryCode,
        ?array $holidays = null,
        ?string $companyId = null,
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
            $holidayDates[(string) $holiday['date']] = true;
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
    public function forget(string $countryCode, int $year, ?string $companyId = null): void
    {
        $cacheKey = sprintf('public-holidays:%s:%d:%s', strtoupper($countryCode), $year, (string) ($companyId ?? 'null'));
        $this->cache->forget($cacheKey);
    }

    /**
     * BUG #1897 — invalide TOUTES les clés d'un pays/année : la clé nationale
     * (company_id = null) ET chaque clé tenant-scopée (les tenants qui ont
     * déjà calculé gardaient un cache périmé jusqu'à 24 h après une
     * confirmation de date islamique ou une édition de férié national).
     */
    public function forgetAllScopes(string $countryCode, int $year): void
    {
        $countryCode = strtoupper(trim($countryCode));

        $this->forget($countryCode, $year, null);

        $tenantIds = Company::query()
            ->where('country', $countryCode)
            ->pluck('id');

        foreach ($tenantIds as $companyId) {
            $this->forget($countryCode, $year, (string) $companyId);
        }
    }
}
