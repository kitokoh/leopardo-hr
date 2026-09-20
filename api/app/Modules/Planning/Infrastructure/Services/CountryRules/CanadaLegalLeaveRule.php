<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7931 — Règles légales de congés : Canada fédéral (CA).
 *
 * // Code canadien du travail (L.R.C. 1985, ch. L-2), art. 184 et 184.01 :
 * //   - moins de 5 ans de service   : 2 semaines (→ 10 jours ouvrables) ;
 * //   - 5 ans et plus, moins de 10  : 3 semaines (→ 15 jours ouvrables) ;
 * //   - 10 ans et plus              : 4 semaines (→ 20 jours ouvrables).
 * // Indemnité de congé annuel (art. 183) : 4 % / 6 % / 8 % du salaire brut
 * // selon les mêmes seuils d'ancienneté.
 * // Périmètre : employeurs sous réglementation FÉDÉRALE (CLC) — les normes
 * // provinciales (Québec, Ontario…) ne sont pas modélisées ici.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7931 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class CanadaLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'CA';
    }

    public function legalAnnualDays(): float
    {
        return 10.0; // 2 semaines = 10 jours ouvrables (barème d'entrée)
    }

    public function legalAnnualDaysForSeniority(float $seniorityYears): float
    {
        if ($seniorityYears >= 10.0) {
            return 20.0; // 4 semaines (art. 184.01(1)c))
        }

        if ($seniorityYears >= 5.0) {
            return 15.0; // 3 semaines (art. 184.01(1)b))
        }

        return 10.0; // 2 semaines (art. 184.01(1)a))
    }

    public function vacationPayRatePercent(float $seniorityYears): float
    {
        if ($seniorityYears >= 10.0) {
            return 8.0;
        }

        if ($seniorityYears >= 5.0) {
            return 6.0;
        }

        return 4.0; // art. 183 « indemnité de congé annuel »
    }

    public function legalSource(): string
    {
        return 'Code canadien du travail (L.R.C. 1985, ch. L-2), art. 183-184.01 — 2/3/4 semaines selon <5/5-10/10+ ans ; indemnité 4/6/8 %.';
    }
}
