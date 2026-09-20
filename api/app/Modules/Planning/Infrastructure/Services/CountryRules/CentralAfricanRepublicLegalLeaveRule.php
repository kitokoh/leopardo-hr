<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Centrafrique (CF).
 *
 * // Code du travail centrafricain (loi n° 09.004 du 29 janvier 2009),
 * // art. 280 s. : 2 jours ouvrables par mois de travail (→ 24 j/an), plafond
 * // de 30 jours ouvrables ; majorations d'ancienneté (2 j par tranche de
 * // 5 ans) et pour mères de famille non modélisées (pilot).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class CentralAfricanRepublicLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'CF';
    }

    public function legalAnnualDays(): float
    {
        return 24.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail centrafricain (loi n° 09.004 du 29/01/2009), art. 280 s. — 24 jours ouvrables/an (2 j ouvrables/mois, plafond 30 j).';
    }
}
