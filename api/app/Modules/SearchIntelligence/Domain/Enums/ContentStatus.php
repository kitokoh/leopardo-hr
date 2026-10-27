<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-6 (#8517) — statut éditorial d'un contenu marketing.
 *
 * `idea → draft → review → approved → published`, plus `to_update`.
 *
 * Deux règles portées ici :
 *  - `published` n'est atteignable QUE depuis `approved` : rien n'est publié
 *    automatiquement, une validation humaine est toujours intercalée ;
 *  - `to_update` sort le contenu du public sans le détruire : un contenu qui a
 *    vieilli reste consultable en interne mais retourne en revue.
 *
 * La migration `2026_10_26_000005` double la règle « published exige une URL »
 * par une contrainte CHECK.
 */
enum ContentStatus: string
{
    case Idea = 'idea';
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';
    case ToUpdate = 'to_update';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Idea => [self::Draft],
            self::Draft => [self::Review],
            self::Review => [self::Approved, self::Draft],
            self::Approved => [self::Published, self::Draft],
            self::Published => [self::ToUpdate],
            self::ToUpdate => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Un contenu éditable sans repasser par la revue. */
    public function isEditable(): bool
    {
        return $this === self::Idea || $this === self::Draft;
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idée',
            self::Draft => 'Brouillon',
            self::Review => 'En relecture',
            self::Approved => 'Validé',
            self::Published => 'Publié',
            self::ToUpdate => 'À mettre à jour',
        };
    }
}
