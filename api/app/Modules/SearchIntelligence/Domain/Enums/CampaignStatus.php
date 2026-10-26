<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-3 (#8514) — statut d'une campagne SEA.
 *
 * La machine à états est volontairement stricte : on ne « relance » pas une
 * campagne terminée (il faut en créer une nouvelle, l'historique de dépense
 * reste attaché à l'ancienne) et on ne met pas en pause un brouillon (il n'a
 * jamais tourné).
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Ended],
            self::Active => [self::Paused, self::Ended],
            self::Paused => [self::Active, self::Ended],
            self::Ended => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Une campagne ne consomme un budget que lorsqu'elle tourne. */
    public function isSpending(): bool
    {
        return $this === self::Active;
    }
}
