<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Cameroun (CM).
 *
 * // Code du travail camerounais (loi n° 92/007 du 14 août 1992), art. 89 :
 * // 1,5 jour ouvrable de congé payé par mois de service effectif
 * // (→ 18 jours ouvrables/an ; 2,5 j/mois pour les moins de 18 ans).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class CameroonLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'CM';
    }

    public function legalAnnualDays(): float
    {
        return 18.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail camerounais (loi n° 92/007 du 14/08/1992), art. 89 — 18 jours ouvrables/an (1,5 j ouvrable/mois).';
    }
}
