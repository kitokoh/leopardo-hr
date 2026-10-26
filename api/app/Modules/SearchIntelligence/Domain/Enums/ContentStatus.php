<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-6 (#8517) — statut d'un contenu marketing.
 *
 * Même philosophie que `AeoQuestionStatus` : un contenu passe par une revue
 * avant publication, et le statut « publié » n'est atteignable qu'avec un
 * auteur et une date — c'est ce que la contrainte CHECK de `content_items`
 * matérialise en base.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview, self::Archived],
            self::InReview => [self::Approved, self::Draft, self::Archived],
            self::Approved => [self::Published, self::Draft, self::Archived],
            self::Published => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::InReview;
    }
}
