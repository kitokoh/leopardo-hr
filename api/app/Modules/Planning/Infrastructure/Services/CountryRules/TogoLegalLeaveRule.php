<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Togo (TG).
 *
 * // Code du travail togolais (loi n° 2021-012 du 18 juin 2021) : congés payés
 * // de 2,5 jours ouvrables par mois de service effectif (→ 30 jours
 * // ouvrables/an) — numéro d'article à confirmer par expert local.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class TogoLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'TG';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail togolais (loi n° 2021-012 du 18/06/2021) — 30 jours ouvrables/an (2,5 j ouvrables/mois ; article à confirmer par expert).';
    }
}
