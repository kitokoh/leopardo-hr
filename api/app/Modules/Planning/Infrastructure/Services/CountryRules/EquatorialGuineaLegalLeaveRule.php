<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Guinée équatoriale (GQ).
 *
 * // Ley General de Trabajo (ley n° 4/2021 du 3 décembre 2021), art. 40 :
 * // un mois de vacances annuelles payées, interprété comme 30 jours
 * // calendaires — valeur la plus communément citée, à confirmer par expert.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class EquatorialGuineaLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'GQ';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Ley General de Trabajo (ley n° 4/2021 du 03/12/2021), art. 40 — 1 mois ≈ 30 jours calendaires/an (2,5 j/mois ; à confirmer par expert).';
    }
}
