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

    /**
     * Droit légal annuel en jours selon l'ancienneté (années de service
     * révolues). Défaut : le droit annuel de base — seuls les pays à barème
     * d'ancienneté (issue #7931 : TR m.53 İş Kanunu, CA CLC art. 184.01)
     * surchargent. Peut retourner 0 si la loi n'ouvre aucun droit avant un
     * seuil d'ancienneté (TR : 1 an minimum).
     */
    public function legalAnnualDaysForSeniority(float $seniorityYears): float;

    /**
     * Taux légal d'indemnité de congés (% du salaire brut) selon l'ancienneté,
     * quand la loi l'exprime ainsi (issue #7931 : CA CLC art. 183 — 4/6/8 %).
     * null = pas de taux légal exprimé en pourcentage pour ce pays.
     */
    public function vacationPayRatePercent(float $seniorityYears): ?float;

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
