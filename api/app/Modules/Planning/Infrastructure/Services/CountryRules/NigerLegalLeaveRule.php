<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Niger (NE).
 *
 * // Code du travail nigérien (loi n° 2012-45 du 25 septembre 2012),
 * // art. 116 s. : 2,5 jours calendaires de congé payé par mois de service
 * // effectif (→ 30 jours calendaires/an) — article à confirmer par expert.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class NigerLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'NE';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail nigérien (loi n° 2012-45 du 25/09/2012), art. 116 s. — 30 jours calendaires/an (2,5 j calendaires/mois ; à confirmer par expert).';
    }
}
