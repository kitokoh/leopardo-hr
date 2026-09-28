<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Shared\Contracts\Payroll\PublicHolidayCalendar;
use Illuminate\Support\Carbon;

/**
 * Issue #5289 — calendrier des jours fériés LÉGAUX par pays, côté congés.
 *
 * Lecture SEULE des fériés nationaux via le contrat partagé
 * `App\Shared\Contracts\Payroll\PublicHolidayCalendar` (#8211 / BOS-023 —
 * plus d'import croisé `Modules/Planning -> Modules/Payroll`, règle
 * d'isolation #5584) :
 *  - fériés nationaux : `company_id IS NULL` (lus par tous les tenants du pays) ;
 *  - fériés récurrents : `is_recurring = true` → appliqués à toutes les
 *    années via `month_day` (cf. #1936), pas seulement à l'année stockée ;
 *  - les fériés d'entreprise (`company_id NOT NULL`) sont hors périmètre
 *    (calendrier légal national).
 *
 * Consommé par le calendrier des congés et par les tests golden par pays.
 */
final class LegalLeaveCalendarService
{
    public function __construct(
        private readonly PublicHolidayCalendar $publicHolidayCalendar,
    ) {}

    /**
     * Fériés légaux (nationaux) d'un pays pour une année.
     *
     * @return array<int, array{date: string, name: string, holiday_type: string}>
     */
    public function legalHolidays(string $countryCode, int $year): array
    {
        $holidays = array_map(
            fn (array $row): array => [
                'date' => $this->effectiveDate($row, $year),
                'name' => $row['name'],
                'holiday_type' => $row['holiday_type'],
            ],
            $this->publicHolidayCalendar->nationalHolidayRows($countryCode, $year),
        );

        usort($holidays, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    /**
     * #1936 — date effective d'un férié pour une année donnée : pour un férié
     * récurrent, l'année demandée remplace l'année stockée (la date stockée
     * est la première occurrence ; `month_day` porte mois-jour). Les lignes
     * récurrentes legacy sans `month_day` dérivent le mois-jour de la date
     * stockée — sans quoi le férié n'est jamais appliqué hors de son année.
     *
     * @param  array{date: string, name: string, holiday_type: string, is_recurring: bool, month_day: string|null}  $row
     */
    private function effectiveDate(array $row, int $year): string
    {
        if ($row['is_recurring']) {
            return sprintf('%04d-%s', $year, $row['month_day'] ?? Carbon::parse($row['date'])->format('m-d'));
        }

        return $row['date'];
    }

    /**
     * Dates (Y-m-d) des fériés légaux d'un pays pour une année.
     *
     * @return array<int, string>
     */
    public function legalHolidayDates(string $countryCode, int $year): array
    {
        return array_map(
            static fn (array $holiday): string => $holiday['date'],
            $this->legalHolidays($countryCode, $year)
        );
    }

    /** Une date donnée est-elle un férié légal national pour ce pays ? */
    public function isLegalHoliday(string $countryCode, Carbon $date): bool
    {
        return in_array($date->format('Y-m-d'), $this->legalHolidayDates($countryCode, $date->year), true);
    }
}
