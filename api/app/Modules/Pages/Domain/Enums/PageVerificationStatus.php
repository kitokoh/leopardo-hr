<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Enums;

use App\Modules\Pages\Domain\Exceptions\InvalidPageVerificationTransitionException;

/**
 * Axe 2 — **vérification de propriété** d'une Page (BC PAGES, #8725).
 *
 * Séparé de la publication ({@see PageStatus}). La vérification prouve que le
 * tenant contrôle réellement le lieu public décrit, pas qu'il a le droit
 * d'éditer une fiche : ce sont trois choses distinctes (compte tenant,
 * capacité d'édition d'un Établissement, preuve d'exploitation du lieu), cf.
 * `docs/design/etablissements-pages.md` §5.
 *
 * Transitions autorisées — toute autre transition lève une exception, ce qui
 * rend impossible un « verified » obtenu par simple affectation :
 *
 * ```
 * unverified ──> pending ──> verified ──> revoked ──> pending
 *                   │  └────> rejected ──> pending
 *                   └───────> disputed ──> verified | rejected | revoked
 * verified ──> disputed
 * ```
 *
 * `revoked` est terminal pour une preuve donnée mais ré-ouvrable (`pending`) :
 * un tenant dont le bail a expiré puis qui en signe un nouveau doit pouvoir
 * redemander une vérification sans intervention en base.
 */
enum PageVerificationStatus: string
{
    case Unverified = 'unverified';

    case Pending = 'pending';

    case Verified = 'verified';

    case Rejected = 'rejected';

    case Disputed = 'disputed';

    case Revoked = 'revoked';

    /**
     * Transitions légales depuis l'état courant.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unverified => [self::Pending],
            self::Pending => [self::Verified, self::Rejected, self::Disputed, self::Unverified],
            self::Verified => [self::Disputed, self::Revoked],
            self::Rejected => [self::Pending, self::Unverified],
            self::Disputed => [self::Verified, self::Rejected, self::Revoked],
            self::Revoked => [self::Pending, self::Unverified],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidPageVerificationTransitionException
     */
    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidPageVerificationTransitionException::between($this->value, $target->value);
        }
    }

    /**
     * La Page est-elle « vérifiée » au sens de l'annuaire (badge public) ?
     */
    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    /**
     * Un dossier est-il ouvert (modération en cours) ? Sert aux compteurs de
     * la console Superadmin (#8729) et aux gardes anti-double-soumission.
     */
    public function isOpenCase(): bool
    {
        return in_array($this, [self::Pending, self::Disputed], true);
    }
}
