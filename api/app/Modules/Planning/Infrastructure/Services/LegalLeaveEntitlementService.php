<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Contracts\LegalLeaveCountryRuleInterface;
use App\Modules\Planning\Infrastructure\Services\CountryRules\LegalLeaveRulesRegistry;
use Illuminate\Support\Carbon;

/**
 * Issue #5289 — droit légal de congés projeté depuis l'ancienneté.
 *
 * Calcul PUR (aucune requête, aucun effet de bord) du droit légal projeté
 * d'un employé pour une année civile :
 *
 *   droit = min(mois travaillés × acquisition mensuelle légale, droit annuel légal)
 *
 * Règles de proratisation documentées (spec #5289 US2) :
 *  - l'ancre d'ancienneté est `employees.contract_start` ; si absente, le
 *    calcul part du 1er janvier de l'année cible ;
 *  - le mois d'embauche compte en entier si l'embauche a lieu le 15 du mois
 *    ou avant (pratique RH courante : l'acquisition porte sur les mois de
 *    service complets) ;
 *  - les mois sont bornés à l'année civile cible (pas de mois avant le
 *    1er janvier de l'année) ;
 *  - le résultat est plafonné au droit annuel légal et arrondi à 2 décimales ;
 *  - pays à barème d'ancienneté (issue #7931 : TR m.53, CA CLC art. 184.01) :
 *    le droit annuel est résolu via `legalAnnualDaysForSeniority()` avec
 *    l'ancienneté au 1er janvier de l'année cible (années de service révolues
 *    AVANT l'année de prise) — pour les pays sans barème, strictement
 *    identique au calcul historique (droit annuel de base / 12).
 */
final class LegalLeaveEntitlementService
{
    /**
     * Mois de service complets d'un employé dans l'année cible.
     */
    public function monthsWorkedInYear(Employee $employee, int $year): int
    {
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::createFromDate($year, 12, 31)->startOfDay();

        $anchor = $employee->contract_start !== null
            ? Carbon::parse($employee->contract_start)->startOfDay()
            : $yearStart;

        if ($anchor->gt($yearEnd)) {
            return 0; // embauche après la fin de l'année cible
        }

        $start = $anchor->greaterThan($yearStart) ? $anchor : $yearStart;

        if ($start->day > 15) {
            // Embauche après le 15 : l'acquisition démarre le mois suivant.
            $start = $start->copy()->addMonthNoOverflow()->firstOfMonth();
        } else {
            $start = $start->copy()->firstOfMonth();
        }

        if ($start->greaterThan($yearEnd)) {
            return 0;
        }

        // Nombre de mois pleins entre `start` (1er du mois) et décembre inclus.
        return max(0, ($year - $start->year) * 12 + (12 - $start->month) + 1);
    }

    /**
     * Ancienneté (années de service, fraction conservée) au 1er janvier de
     * l'année cible — convention #7931 : le barème d'ancienneté (TR/CA)
     * s'applique sur les années révolues AVANT l'année de prise du congé.
     */
    public function seniorityYearsAtYearStart(Employee $employee, int $year): float
    {
        if ($employee->contract_start === null) {
            return 0.0;
        }

        $anchor = Carbon::parse($employee->contract_start)->startOfDay();
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfDay();

        if ($anchor->greaterThanOrEqualTo($yearStart)) {
            return 0.0;
        }

        return round((float) $anchor->diffInYears($yearStart, true), 4);
    }

    /**
     * Droit légal projeté (jours) pour l'année cible, plafonné au droit annuel.
     *
     * @param  string|null  $countryCode  code pays ISO ; null → pays de l'entreprise
     */
    public function projectedEntitlement(Employee $employee, int $year, ?string $countryCode = null, ?LegalLeaveCountryRuleInterface $rule = null): float
    {
        $resolvedRule = $rule ?? LegalLeaveRulesRegistry::resolve($countryCode ?? '');
        $months = $this->monthsWorkedInYear($employee, $year);

        // Barème d'ancienneté (#7931) : droit annuel résolu par l'ancienneté au
        // 1er janvier ; pays sans barème → droit annuel de base (aucun écart
        // avec le calcul historique : accrualDaysPerMonth = annuel / 12).
        $annualDays = $resolvedRule->legalAnnualDaysForSeniority($this->seniorityYearsAtYearStart($employee, $year));
        $monthlyAccrual = round($annualDays / 12, 4);

        return round(min($months * $monthlyAccrual, $annualDays), 2);
    }
}
