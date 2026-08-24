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
 *  - le résultat est plafonné au droit annuel légal et arrondi à 2 décimales.
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
     * Droit légal projeté (jours) pour l'année cible, plafonné au droit annuel.
     *
     * @param  string|null  $countryCode  code pays ISO ; null → pays de l'entreprise
     */
    public function projectedEntitlement(Employee $employee, int $year, ?string $countryCode = null, ?LegalLeaveCountryRuleInterface $rule = null): float
    {
        $resolvedRule = $rule ?? LegalLeaveRulesRegistry::resolve($countryCode ?? '');
        $months = $this->monthsWorkedInYear($employee, $year);

        return round(min($months * $resolvedRule->accrualDaysPerMonth(), $resolvedRule->legalAnnualDays()), 2);
    }
}
