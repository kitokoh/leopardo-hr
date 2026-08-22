<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #5289 — Règles légales de congés : Tunisie (TN).
 *
 * // Convention collective-cadre de 1966 / Code du travail tunisien :
 * // 30 jours de congé annuel payé par an (acquisition 2,5 j/mois).
 * // Indemnité compensatrice au départ (Code du travail, art. 65 s.).
 * //
 * // confidenceLevel : 'pilot' — valeur issue de l'issue #5289, à confirmer
 * // par le RH pilote avant certification 'production'.
 */
final class TunisiaLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'TN';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Convention collective-cadre 1966 — 30 jours/an (2,5 j/mois) ; Code du travail tunisien (indemnité au départ).';
    }
}
