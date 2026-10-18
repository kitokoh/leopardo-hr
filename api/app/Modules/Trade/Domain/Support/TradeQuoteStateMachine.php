<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Support;

use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;

/**
 * Machine a etats d'une offre commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * Cycle : draft -> submitted -> under_review -> negotiating ->
 * (accepted | rejected | withdrawn) ; submitted -> expired.
 *
 * Regles notables :
 *  - `accepted` est terminal — c'est le seul etat qui autorise la creation
 *    d'une commande (`allowsOrderCreation()`), et une offre ne se re-accepte
 *    pas ;
 *  - une revision (`revise`) ne RE-OUVRE jamais l'offre : elle cree une
 *    nouvelle version, la courante passant a `withdrawn` (historique de
 *    negociation preserve) ;
 *  - `expired` se constate depuis un etat soumis, jamais depuis un brouillon.
 *
 * Classe PURE, testable en unite.
 */
final class TradeQuoteStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['submitted', 'withdrawn'],
        'submitted' => ['under_review', 'negotiating', 'accepted', 'rejected', 'withdrawn', 'expired'],
        'under_review' => ['negotiating', 'accepted', 'rejected', 'withdrawn', 'expired'],
        'negotiating' => ['under_review', 'accepted', 'rejected', 'withdrawn', 'expired'],
        // Etats terminaux : aucune transition sortante.
        'accepted' => [],
        'rejected' => [],
        'expired' => [],
        'withdrawn' => [],
    ];

    public function assertCanTransitionTo(TradeQuoteStatus $from, TradeQuoteStatus $to): void
    {
        if (! $this->canTransitionTo($from, $to)) {
            throw new InvalidTradeStateTransitionException('offre', $from->value, $to->value);
        }
    }

    public function canTransitionTo(TradeQuoteStatus $from, TradeQuoteStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(TradeQuoteStatus $from): array
    {
        return self::ALLOWED_TRANSITIONS[$from->value];
    }
}
