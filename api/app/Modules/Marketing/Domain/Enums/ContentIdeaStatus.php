<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — cycle de vie d'une idée / brief de contenu (brief §B).
 *
 * `published` n'est JAMAIS posé par une action automatique : il résulte
 * d'une publication réussie côté API ou d'une confirmation humaine
 * explicite (mode assisté).
 */
enum ContentIdeaStatus: string
{
    case Idea = 'idea';
    case Briefing = 'briefing';
    case InProduction = 'in_production';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idée',
            self::Briefing => 'En brief',
            self::InProduction => 'En production',
            self::InReview => 'En validation',
            self::Approved => 'Approuvée',
            self::Scheduled => 'Planifiée',
            self::Published => 'Publiée',
            self::Cancelled => 'Annulée',
        };
    }

    /** Statuts encore ouverts au travail de production. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Published, self::Cancelled], true);
    }
}
