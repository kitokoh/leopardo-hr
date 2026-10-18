<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

use App\Modules\Trade\Domain\Support\TradeQuoteStateMachine;

/**
 * Statut d'une offre commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * Objet unique servant de reponse a une RFQ (`incoming`) et d'offre proactive
 * (`outgoing`). Cycle : draft → submitted → under_review → negotiating →
 * (accepted | rejected | withdrawn) ; submitted → expired (echeance de
 * validite atteinte). `accepted` est terminal et permet la creation d'une
 * commande.
 *
 * Transitions verrouillees par {@see TradeQuoteStateMachine}.
 */
enum TradeQuoteStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Negotiating = 'negotiating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Withdrawn], true);
    }

    /**
     * Une offre encore en negociation peut etre revue (nouvelle version).
     */
    public function isRevisionable(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::Negotiating], true);
    }

    /**
     * Seule une offre acceptee produit une commande.
     */
    public function allowsOrderCreation(): bool
    {
        return $this === self::Accepted;
    }

    public function allowsComparison(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::Negotiating, self::Accepted], true);
    }
}
