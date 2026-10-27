<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-2 (#8513) — cycle de vie d'une anomalie SEO, aligné sur le flux de la
 * mission (`03_APPFLOW.md`, flux 3) :
 *
 * ```
 * detected → recommended → approved → applied → verified
 *        ↘ dismissed (avec justification obligatoire)
 * ```
 *
 * La machine à états est explicite parce que le workflow est AUDITÉ : chaque
 * transition écrit une ligne `AuditLog`, et une transition non prévue serait
 * une donnée d'audit mensongère.
 *
 * Deux garanties portées ici :
 *  - on ne peut pas `apply` une correction qui n'a pas été `approved` — c'est
 *    ce qui empêche le système de modifier la production sans validation ;
 *  - `dismissed` exige une justification (`assertDismissable`) : une anomalie
 *    écartée sans motif est indistinguable d'un oubli.
 */
enum SeoIssueStatus: string
{
    case Detected = 'detected';
    case Recommended = 'recommended';
    case Approved = 'approved';
    case Applied = 'applied';
    case Verified = 'verified';
    case Dismissed = 'dismissed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Detected => [self::Recommended, self::Dismissed],
            self::Recommended => [self::Approved, self::Dismissed],
            self::Approved => [self::Applied, self::Dismissed],
            // `applied` → `detected` : le re-scan a retrouvé l'anomalie, la
            // correction n'a pas tenu. C'est un retour légitime, pas une
            // régression de données.
            self::Applied => [self::Verified, self::Detected],
            self::Verified, self::Dismissed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Une anomalie dans cet état demande encore une action humaine. */
    public function isActionable(): bool
    {
        return $this === self::Detected || $this === self::Recommended;
    }

    /** État terminal : plus rien à faire, l'anomalie sort de la file. */
    public function isTerminal(): bool
    {
        return $this === self::Verified || $this === self::Dismissed;
    }

    /**
     * Une anomalie écartée doit porter sa justification : sans motif, le
     * `dismissed` est indiscernable d'un abandon.
     */
    public function requiresDismissReason(): bool
    {
        return $this === self::Dismissed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Détectée',
            self::Recommended => 'Correction proposée',
            self::Approved => 'Correction approuvée',
            self::Applied => 'Correction appliquée',
            self::Verified => 'Vérifiée',
            self::Dismissed => 'Écartée',
        };
    }
}
