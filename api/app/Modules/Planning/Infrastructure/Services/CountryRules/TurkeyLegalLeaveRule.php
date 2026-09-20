<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7931 — Règles légales de congés : Turquie (TR).
 *
 * // İş Kanunu n° 4857, m. 53 : le congé annuel payé s'ouvre après UN AN
 * // d'ancienneté (aucun droit légal avant), en jours OUVRABLES (iş günü,
 * // m. 56) selon le barème :
 * //   - 1 à 5 ans d'ancienneté (5 ans inclus)  : 14 jours ;
 * //   - plus de 5 ans et moins de 15 ans       : 20 jours ;
 * //   - 15 ans et plus                         : 26 jours.
 * // (Salariés ≤ 18 ans ou ≥ 50 ans : minimum 20 jours — non modélisé, pilot.)
 * // Le droit annuel « de base » exposé est le barème d'entrée (14 jours).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7931 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class TurkeyLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'TR';
    }

    public function legalAnnualDays(): float
    {
        return 14.0;
    }

    public function legalAnnualDaysForSeniority(float $seniorityYears): float
    {
        if ($seniorityYears < 1.0) {
            return 0.0; // m. 53 : aucun droit légal avant un an d'ancienneté
        }

        if ($seniorityYears <= 5.0) {
            return 14.0; // 1 à 5 ans (5 ans INCLUS)
        }

        if ($seniorityYears < 15.0) {
            return 20.0; // plus de 5 ans et moins de 15 ans
        }

        return 26.0; // 15 ans et plus
    }

    public function legalSource(): string
    {
        return 'İş Kanunu n° 4857, m. 53 (jours ouvrables, m. 56) — 14 j (1-5 ans incl.), 20 j (5-15 ans), 26 j (15 ans et +).';
    }
}
