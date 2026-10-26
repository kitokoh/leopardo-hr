<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

use App\Modules\SearchIntelligence\Domain\Exceptions\ReviewRequiredException;

/**
 * GSI-4 (#8515) — statut éditorial d'une réponse AEO.
 *
 * La règle « révision humaine obligatoire » est portée ICI, dans le domaine,
 * et non seulement dans un contrôleur : aucune autre couche ne peut publier
 * une réponse qui n'a pas été relue. La migration `aeo_questions` double la
 * règle par une contrainte CHECK, pour qu'un INSERT SQL direct ne la contourne
 * pas non plus (défense en profondeur).
 */
enum AeoQuestionStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Published = 'published';
    case Rejected = 'rejected';

    /**
     * Transitions autorisées : un brouillon ne peut pas sauter directement à
     * `published`. Passer par `reviewed` est ce qui rend la révision humaine
     * observable (qui, quand) et auditable.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Reviewed, self::Rejected],
            self::Reviewed => [self::Published, self::Draft, self::Rejected],
            self::Rejected => [self::Draft],
            self::Published => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Garde de publication : vérifie la transition ET la présence des preuves
     * de révision. Lève plutôt que de retourner faux — l'appelant qui tente
     * une publication sans révision est dans un état de programmation
     * incorrect, pas dans un cas nominal.
     */
    public function assertPublishable(?int $reviewedBy, ?string $answer): void
    {
        if (! $this->canTransitionTo(self::Published)) {
            throw new ReviewRequiredException(
                sprintf('Une réponse AEO « %s » ne peut pas être publiée sans passer par la révision.', $this->value)
            );
        }

        if ($reviewedBy === null || $answer === null || trim($answer) === '') {
            throw new ReviewRequiredException(
                'Une réponse AEO ne peut être publiée sans relecteur, date de révision et contenu.'
            );
        }
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
