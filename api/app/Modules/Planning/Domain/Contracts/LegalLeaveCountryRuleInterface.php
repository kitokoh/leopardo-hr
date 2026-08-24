<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Contracts;

/**
 * Issue #5289 — Règle légale de congés d'un pays.
 *
 * Contrat des règles légales de congés annuels par pays (DZ/MA/TN/SN d'abord).
 * Chaque implémentation porte sa référence légale (source) et son niveau de
 * confiance : `pilot` = à valider par un RH/expert pilote, `production` =
 * validé par un expert local (constitution §III).
 *
 * Les valeurs sont exprimées en JOURS (fractions autorisées pour les
 * acquisitions mensuelles, ex. 2,5 j/mois pour la DZ).
 */
interface LegalLeaveCountryRuleInterface
{
    /** Code ISO-3166 alpha-2 (majuscule) du pays. */
    public function countryCode(): string;

    /** Droit légal annuel en jours (ex. 30 pour la DZ). */
    public function legalAnnualDays(): float;

    /** Acquisition mensuelle légale en jours (droit annuel / 12). */
    public function accrualDaysPerMonth(): float;

    /** Le report du solde non pris sur l'année suivante est-il autorisé par la loi ? */
    public function carryForwardAllowed(): bool;

    /** Plafond légal de report en jours (null = pas de plafond légal explicite). */
    public function carryForwardMaxDays(): ?float;

    /** La monétisation (indemnité compensatrice de congés non pris) est-elle autorisée ? */
    public function monetizationAllowed(): bool;

    /** Référence légale textuelle (loi, article, convention). */
    public function legalSource(): string;

    /** Niveau de confiance : 'pilot' (à valider) | 'production' (validé expert). */
    public function confidenceLevel(): string;
}
