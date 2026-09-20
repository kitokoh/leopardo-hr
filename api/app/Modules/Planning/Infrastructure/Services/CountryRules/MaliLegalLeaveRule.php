<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Mali (ML).
 *
 * // Code du travail malien (loi n° 92-020 du 23 septembre 1992), art. L.148 s. :
 * // 2,5 jours de congé par mois de travail accompli, jours non ouvrables
 * // compris (→ 30 jours/an).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class MaliLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'ML';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail malien (loi n° 92-020 du 23/09/1992), art. L.148 s. — 30 jours/an (2,5 j/mois, jours non ouvrables compris).';
    }
}
