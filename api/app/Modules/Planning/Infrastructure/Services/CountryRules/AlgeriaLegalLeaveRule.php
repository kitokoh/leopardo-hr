<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #5289 — Règles légales de congés : Algérie (DZ).
 *
 * // Loi n° 90-11 du 21 avril 1990 relative aux relations de travail, art. 14 :
 * // 30 jours ouvrables de congé annuel payé ; acquisition 2,5 jours/mois.
 * // Report : usage admis (congés non pris reportables) ; monétisation :
 * // indemnité compensatrice au départ (art. 18 s.).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #5289, à confirmer
 * // par le RH pilote (recette, DoD #5289) avant certification 'production'.
 */
final class AlgeriaLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'DZ';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Loi n° 90-11 du 21 avril 1990 (relations de travail), art. 14 — 30 jours ouvrables/an (2,5 j/mois).';
    }
}
