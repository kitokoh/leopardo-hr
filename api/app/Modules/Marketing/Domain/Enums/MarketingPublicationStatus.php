<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — statut d'une publication par plateforme (brief §E).
 *
 * Ces statuts sont DISTINCTS de ceux de `SocialPost` (socle existant) :
 * `SocialPost` porte l'état agrégé du post technique, ici on porte l'état
 * du projet éditorial, plateforme par plateforme.
 */
enum MarketingPublicationStatus: string
{
    case Idea = 'idea';
    case InPreparation = 'in_preparation';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';
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
            self::InPreparation => 'En préparation',
            self::InReview => 'En validation',
            self::Approved => 'Approuvée',
            self::Scheduled => 'Planifiée',
            self::Published => 'Publiée',
            self::Failed => 'Échec',
            self::Cancelled => 'Annulée',
        };
    }
}
