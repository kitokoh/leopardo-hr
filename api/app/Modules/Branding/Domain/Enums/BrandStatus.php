<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Enums;

/**
 * Statut de publication d'une Brand (BC-41, #9578).
 *
 * ## Trois états, deux axes
 *
 * `draft` et `published` sont l'axe de PUBLICATION. `suspended` est un état
 * terminal de modération : une Brand suspendue ne redevient jamais `published`
 * par une simple republication — il faut d'abord la réactiver explicitement.
 * C'est la leçon de BC PAGES (#8729) : un état de suspension qui se lève tout
 * seul n'est pas une suspension.
 *
 * ## Pourquoi un enum et pas une string
 *
 * Le statut conditionne l'accès public. Une valeur inventée (`'live'`,
 * `'active'`) doit échouer au chargement, pas produire une Brand qui ne
 * s'affiche jamais sans que personne ne comprenne pourquoi. `from()` lève,
 * `tryFrom()` retourne null — fail-closed par construction.
 */
enum BrandStatus: string
{
    /** Brouillon : visible du tenant uniquement, jamais du public. */
    case Draft = 'draft';

    /** Publiée : servie sur sa surface publique si le tenant est accessible. */
    case Published = 'published';

    /** Suspendue par la plateforme : jamais servie, quel que soit le reste. */
    case Suspended = 'suspended';

    /**
     * La Brand est-elle servie sur sa surface publique ?
     *
     * Seul `published` l'est. `suspended` est explicitement exclu même si une
     * implémentation distraite le considérait comme « publié puis modéré ».
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    /**
     * Transitions autorisées.
     *
     * `suspended` n'a qu'une sortie : `draft`. On ne republie jamais une Brand
     * suspendue directement — le tenant doit repasser par un brouillon
     * conscient, ce qui laisse une trace et évite qu'une levée de suspension
     * remette en ligne du contenu qui n'a pas été revu.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Suspended],
            self::Published => [self::Draft, self::Suspended],
            self::Suspended => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws \DomainException
     */
    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'BRAND_STATUS_TRANSITION_FORBIDDEN: %s -> %s',
                $this->value,
                $target->value,
            ));
        }
    }
}
