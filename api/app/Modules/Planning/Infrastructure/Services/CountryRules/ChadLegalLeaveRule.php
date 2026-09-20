<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Tchad (TD).
 *
 * // Code du travail tchadien (loi n° 038/PR/96 du 11 décembre 1996), art. 212 :
 * // 2 jours ouvrables de congé payé par mois de travail effectif
 * // (→ 24 jours ouvrables/an) — valeur communément citée, à confirmer par expert.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class ChadLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'TD';
    }

    public function legalAnnualDays(): float
    {
        return 24.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail tchadien (loi n° 038/PR/96 du 11/12/1996), art. 212 — 24 jours ouvrables/an (2 j ouvrables/mois ; à confirmer par expert).';
    }
}
