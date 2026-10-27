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
 *
 * `AwaitingValidation` est le point clé : **`draft → active` n'existe pas**.
 * Une campagne qui dépense de l'argent réel doit passer par une validation
 * humaine explicite ; le chemin court est absent de la machine à états, pas
 * seulement de l'interface.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case AwaitingValidation = 'awaiting_validation';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // Draft → Active est ABSENT par conception : voir le docblock.
            self::Draft => [self::AwaitingValidation, self::Ended],
            self::AwaitingValidation => [self::Active, self::Draft, self::Ended],
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

    /** Seul `AwaitingValidation` peut être validé — et donc activé. */
    public function canBeValidated(): bool
    {
        return $this === self::AwaitingValidation;
    }
}
