<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Burkina Faso (BF).
 *
 * // Code du travail burkinabè (loi n° 028-2008/AN du 13 mai 2008), art. 156 :
 * // 2,5 jours calendaires de congé payé par mois de service effectif
 * // (→ 30 jours calendaires/an).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class BurkinaFasoLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'BF';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail burkinabè (loi n° 028-2008/AN du 13/05/2008), art. 156 — 30 jours calendaires/an (2,5 j calendaires/mois).';
    }
}
