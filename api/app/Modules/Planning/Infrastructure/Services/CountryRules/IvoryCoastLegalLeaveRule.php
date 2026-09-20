<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Côte d’Ivoire (CI).
 *
 * // Code du travail ivoirien (loi n° 2015-532 du 20 juillet 2015), art. 25.1 :
 * // 2,2 jours ouvrables de congé payé par mois de service effectif
 * // (→ 26,4 jours ouvrables/an).
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class IvoryCoastLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'CI';
    }

    public function legalAnnualDays(): float
    {
        return 26.4;
    }

    public function legalSource(): string
    {
        return 'Code du travail ivoirien (loi n° 2015-532 du 20/07/2015), art. 25.1 — 26,4 jours ouvrables/an (2,2 j ouvrables/mois).';
    }
}
