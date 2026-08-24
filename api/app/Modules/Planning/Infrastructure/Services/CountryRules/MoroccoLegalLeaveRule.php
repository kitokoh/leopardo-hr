<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #5289 — Règles légales de congés : Maroc (MA).
 *
 * // Code du travail marocain (loi n° 65-99), art. 231 : 1,5 jour/mois de
 * // service pour les salariés de moins de 18 ans, 2 jours/mois au-delà
 * // (→ 24 jours ouvrables/an). Art. 256 : indemnité compensatrice au départ.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #5289, à confirmer
 * // par le RH pilote avant certification 'production'.
 */
final class MoroccoLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'MA';
    }

    public function legalAnnualDays(): float
    {
        return 24.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail marocain (loi n° 65-99), art. 231 — 24 jours ouvrables/an (2 j/mois).';
    }
}
