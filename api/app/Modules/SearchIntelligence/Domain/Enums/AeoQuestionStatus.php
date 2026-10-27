<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

use App\Modules\SearchIntelligence\Domain\Exceptions\ReviewRequiredException;

/**
 * GSI-4 (#8515) — statut éditorial d'une réponse AEO.
 *
 * `draft → in_review → approved → published`, plus `to_update` (le contenu a
 * vieilli et doit repasser en revue) et `rejected`.
 *
 * La règle « publication impossible sans validation humaine » est portée ICI :
 * `publish` ne peut pas être atteint depuis `draft` ou `in_review`, il faut
 * passer par `approved`. Et `approved` exige qu'une réponse ait été FIGÉE
 * (`answer_approved`) par un relecteur identifié — c'est ce figé qui est
 * publié, pas le brouillon qui aurait pu changer depuis.
 *
 * La migration `2026_10_26_000005` double la règle par une contrainte CHECK,
 * pour qu'un INSERT SQL direct ne la contourne pas.
 */
enum AeoQuestionStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case ToUpdate = 'to_update';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview, self::Rejected],
            self::InReview => [self::Approved, self::Draft, self::Rejected],
            self::Approved => [self::Published, self::Draft, self::Rejected],
            // Une réponse publiée ne s'édite pas sur place : elle repasse en
            // `to_update`, ce qui la sort du public tant qu'elle n'est pas
            // revalidée.
            self::Published => [self::ToUpdate],
            self::ToUpdate => [self::InReview, self::Draft],
            self::Rejected => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Garde de publication. Lève plutôt que de retourner faux : tenter de
     * publier sans validation est un défaut de programmation, pas un cas
     * nominal à gérer silencieusement.
     *
     * @throws ReviewRequiredException
     */
    public function assertPublishable(?int $approvedBy, ?string $answerApproved): void
    {
        if (! $this->canTransitionTo(self::Published)) {
            throw new ReviewRequiredException(sprintf(
                'Une réponse AEO au statut « %s » ne peut pas être publiée sans passer par la validation humaine.',
                $this->value,
            ));
        }

        if ($approvedBy === null || $answerApproved === null || trim($answerApproved) === '') {
            throw new ReviewRequiredException(
                'Une réponse AEO ne peut être publiée sans relecteur identifié et réponse validée figée.',
            );
        }
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::InReview => 'En relecture',
            self::Approved => 'Validée',
            self::Published => 'Publiée',
            self::ToUpdate => 'À mettre à jour',
            self::Rejected => 'Refusée',
        };
    }
}
