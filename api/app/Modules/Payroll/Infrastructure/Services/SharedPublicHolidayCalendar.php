<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Shared\Contracts\Payroll\PublicHolidayCalendar;
use Illuminate\Support\Carbon;

/**
 * Implémentation BC-07 du contrat partagé `PublicHolidayCalendar` (#8211 /
 * BOS-023) : délègue le calcul des jours ouvrés à `PublicHolidayService`
 * (cache 24 h et fusion des fêtes islamiques mobiles inchangés) et expose
 * les fériés nationaux en lignes pures — le modèle Eloquent ne quitte pas
 * le module.
 */
final class SharedPublicHolidayCalendar implements PublicHolidayCalendar
{
    public function __construct(
        private readonly PublicHolidayService $publicHolidays,
    ) {}

    public function nationalHolidayRows(string $countryCode, int $year): array
    {
        return PublicHoliday::query()
            ->where('country_code', strtoupper($countryCode))
            ->whereNull('company_id')
            ->where(function ($query) use ($year): void {
                $query->where('year', $year)->orWhere('is_recurring', true);
            })
            ->orderBy('date')
            ->get(['date', 'name', 'holiday_type', 'is_recurring', 'month_day'])
            ->map(fn (PublicHoliday $holiday): array => [
                'date' => $holiday->date->toDateString(),
                'name' => (string) $holiday->name,
                'holiday_type' => (string) $holiday->holiday_type,
                'is_recurring' => (bool) $holiday->is_recurring,
                'month_day' => $holiday->month_day,
            ])
            ->all();
    }

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
}
