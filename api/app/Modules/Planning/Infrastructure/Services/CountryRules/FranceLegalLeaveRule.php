<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #7931 — Règles légales de congés : France (FR).
 *
 * // Code du travail français, art. L3141-3 : 2,5 jours ouvrables de congé
 * // par mois de travail effectif chez le même employeur, sans que la durée
 * // totale du congé exigible puisse excéder 30 jours ouvrables.
 * //
 * // confidenceLevel : 'pilot' — valeurs issues de l'issue #7931 (lot BC-06),
 * // à confirmer par un RH/expert local avant certification 'production'.
 */
final class FranceLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'FR';
    }

    public function legalAnnualDays(): float
    {
        return 30.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail français, art. L3141-3 — 30 jours ouvrables/an (2,5 j ouvrables/mois).';
    }
}
