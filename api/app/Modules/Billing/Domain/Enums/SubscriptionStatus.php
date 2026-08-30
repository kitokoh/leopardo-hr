<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * États d'une souscription plateforme (DEP-BC21 #5897).
 *
 * Machine à états explicite, alignée sur la contrainte CHECK
 * `subscriptions_status_check` (trial|active|past_due|cancelled|expired) :
 *
 *   trial ────► active ────► past_due ────► expired
 *     │          │  ▲          │  ▲            │
 *     │          │  └──────────┘  └────────────┘ (renouvellement)
 *     └──────────┴────────► cancelled ──► active (réactivation)
 *
 * `cancelled` n'est PAS terminal : la réactivation d'une souscription
 * résiliée est un flux produit supporté (`POST /billing/subscription/renew`,
 * nouvel abonnement Stripe) — la transition `cancelled → active` est donc
 * légale et gardée comme toutes les autres. `expired` est l'état de défaut
 * appliqué par la politique explicite de recouvrement
 * (`billing:enforce-delinquency`) — l'ENFORCEMENT opérationnel (accès coupé)
 * est porté par `companies.status` (active/suspended), distinct de l'état de
 * la souscription.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';

    case Active = 'active';

    case PastDue = 'past_due';

    case Expired = 'expired';

    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trial => [self::Active, self::Cancelled, self::Expired],
            self::Active => [self::PastDue, self::Cancelled, self::Expired],
            self::PastDue => [self::Active, self::Expired, self::Cancelled],
            self::Expired => [self::Active, self::Cancelled],
            self::Cancelled => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
