<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7930 — Règles légales de congés : Congo-Brazzaville (CG).
 *
 * // Code du travail congolais (loi n° 45-75 du 15 mars 1975) : congé annuel
 * // payé de 26 jours ouvrables par année de service effectif (≈ 2,17 j/mois),
 * // + majorations d'ancienneté (2 j par tranche de 5 ans) — numéro d'article
 * // à confirmer par expert local.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7930 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class CongoLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'CG';
    }

    public function legalAnnualDays(): float
    {
        return 26.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail congolais (loi n° 45-75 du 15/03/1975) — 26 jours ouvrables/an (≈ 2,17 j/mois ; article à confirmer par expert).';
    }
}
