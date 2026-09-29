<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Shared\Contracts\Payroll\PublicHolidayCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Adapter du contrat partagé `PublicHolidayCalendar` (#8211, BOS-023).
 *
 * Le module Payroll est propriétaire de la table `public_holidays` : toute
 * lecture cross-module passe par ce contrat au lieu d'importer le modèle ou
 * le service directement (règle d'isolation #5584).
 *
 * - `workingDaysBetween()` délègue au service canonique (résolu via le
 *   binding existant d'`AppServiceProvider` : cache Redis 24 h + fusion du
 *   calendrier islamique) ;
 * - `nationalHolidays()` reprend à l'identique la requête historique du
 *   `LegalLeaveCalendarService` (Planning, #5289) : fériés nationaux fixes,
 *   sans fusion islamique ni cache — comportement strictement préservé.
 */
final class PublicHolidayCalendarAdapter implements PublicHolidayCalendar
{
    public function __construct(
        private readonly PublicHolidayService $publicHolidays,
    ) {}

    public function workingDaysBetween(
        Carbon $start,
        Carbon $end,
        string $countryCode,
        ?array $holidays = null,
        ?string $companyId = null,
        array $restDays = [6, 7],
    ): float {
        return $this->publicHolidays->workingDaysBetween(
            $start,
            $end,
            $countryCode,
            $holidays,
            $companyId,
            $restDays,
        );
    }

    public function nationalHolidays(string $countryCode, int $year): array
    {
        return PublicHoliday::query()
            ->where('country_code', strtoupper($countryCode))
            ->whereNull('company_id')
            ->where(function (Builder $query) use ($year): void {
                $query->where('year', $year)->orWhere('is_recurring', true);
            })
            ->orderBy('date')
            ->get(['date', 'name', 'holiday_type', 'is_recurring', 'month_day'])
            ->map(
                /** @return array{date: string, name: string, holiday_type: string, is_recurring: bool, month_day: string|null} */
                fn (PublicHoliday $holiday): array => [
                    'date' => $holiday->date->toDateString(),
                    'name' => (string) $holiday->name,
                    'holiday_type' => (string) $holiday->holiday_type,
                    'is_recurring' => (bool) $holiday->is_recurring,
                    'month_day' => $holiday->month_day !== null ? (string) $holiday->month_day : null,
                ],
            )
            ->all();
    }
}
