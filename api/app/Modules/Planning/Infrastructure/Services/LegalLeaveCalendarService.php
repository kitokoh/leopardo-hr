<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Support\Carbon;

/**
 * Issue #5289 — calendrier des jours fériés LÉGAUX par pays, côté congés.
 *
 * Lecture SEULE de la table globale `public_holidays` (module Payroll —
 * aucun code Payroll modifié, cf. anti-collision #5289) :
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
    /**
     * Fériés légaux (nationaux) d'un pays pour une année.
     *
     * @return array<int, array{date: string, name: string, holiday_type: string}>
     */
    public function legalHolidays(string $countryCode, int $year): array
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
                'date' => $this->effectiveDate($holiday, $year),
                'name' => (string) $holiday->name,
                'holiday_type' => (string) $holiday->holiday_type,
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * #1936 — date effective d'un férié pour une année donnée : pour un férié
     * récurrent, l'année demandée remplace l'année stockée (la date stockée
     * est la première occurrence ; `month_day` porte mois-jour). Les lignes
     * récurrentes legacy sans `month_day` dérivent le mois-jour de la date
     * stockée — sans quoi le férié n'est jamais appliqué hors de son année.
     */
    private function effectiveDate(PublicHoliday $holiday, int $year): string
    {
        if ($holiday->is_recurring) {
            return sprintf('%04d-%s', $year, $holiday->month_day ?? $holiday->date->format('m-d'));
        }

        return $holiday->date->toDateString();
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
