<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Bénin (BJ).
 *
 * // Code du travail béninois (loi n° 98-004 du 27 janvier 1998), art. 158 :
 * // 2 jours ouvrables de congé payé par mois de service effectif
 * // (→ 24 jours ouvrables/an).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class BeninLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'BJ';
    }

    public function legalAnnualDays(): float
    {
        return 24.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail béninois (loi n° 98-004 du 27/01/1998), art. 158 — 24 jours ouvrables/an (2 j ouvrables/mois).';
    }
}
