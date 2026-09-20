<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

use App\Modules\Planning\Domain\Contracts\LegalLeaveCountryRuleInterface;

/**
 * Issue #5289 — base commune des règles légales de congés par pays.
 *
 * Fournit les calculs dérivés (acquisition mensuelle depuis le droit annuel)
 * et les défauts conservateurs pour le report/monétisation. Chaque pays
 * surcharge ce qu'il doit, porte sa référence légale et son confidenceLevel.
 */
abstract class AbstractLegalLeaveCountryRule implements LegalLeaveCountryRuleInterface
{
    public function accrualDaysPerMonth(): float
    {
        // Droit annuel / 12 mois — fraction conservée (ex. 2,5 j DZ).
        return round($this->legalAnnualDays() / 12, 4);
    }

    public function legalAnnualDaysForSeniority(float $seniorityYears): float
    {
        // Défaut : pas de barème d'ancienneté — droit annuel de base (issue
        // #7931 : TR/CA surchargent avec leur barème légal).
        return $this->legalAnnualDays();
    }

    public function vacationPayRatePercent(float $seniorityYears): ?float
    {
        return null; // pas de taux légal en % par défaut (CA : 4/6/8 %, #7931)
    }

    public function carryForwardAllowed(): bool
    {
        return true;
    }

    public function carryForwardMaxDays(): ?float
    {
        return null; // pas de plafond légal explicite par défaut
    }

    public function monetizationAllowed(): bool
    {
        return true;
    }

    public function confidenceLevel(): string
    {
        return 'pilot'; // à valider par un RH/expert pilote (recette issue #5289)
    }
}
