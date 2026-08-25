<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

/**
 * Issue #5289 — Règles légales de congés : Sénégal (SN).
 *
 * // Code du travail sénégalais (loi n° 97-17), art. L.151 s. : congé annuel
 * // 2 jours ouvrables/mois de service effectif, + 1 jour supplémentaire par
 * // tranche de 5 ans d'ancienneté (→ jusqu'à 26 jours/an).
 * // Valeur retenue (issue #5289) : 26 jours/an (≈ 2,17 j/mois).
 * //
 * // confidenceLevel : 'pilot' — valeur issue de l'issue #5289, à confirmer
 * // par le RH pilote avant certification 'production'.
 */
final class SenegalLegalLeaveRule extends AbstractLegalLeaveCountryRule
{
    public function countryCode(): string
    {
        return 'SN';
    }

    public function legalAnnualDays(): float
    {
        return 26.0;
    }

    public function legalSource(): string
    {
        return 'Code du travail sénégalais (loi n° 97-17), art. L.151 s. — 26 jours/an (2 j/mois + ancienneté).';
    }
}
