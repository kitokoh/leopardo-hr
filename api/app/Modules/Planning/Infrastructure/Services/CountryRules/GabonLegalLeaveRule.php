<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Gabon (GA).
 *
 * // Code du travail gabonais (loi n° 022/2021 du 19 novembre 2021), art. 185 :
 * // 2 jours ouvrables de congé par mois de service effectif
 * // (→ 24 jours ouvrables/an ; 2,5 j/mois pour les moins de 18 ans).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class GabonLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'GA';
    }

    public function legalAnnualDays(): float
    {
        return 24.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail gabonais (loi n° 022/2021 du 19/11/2021), art. 185 — 24 jours ouvrables/an (2 j ouvrables/mois).';
    }
}
