<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Support;

use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;

/**
 * Machine a etats d'une commande — BC-36 TRADE, TRADE-004/#8479.
 *
 * Cycle : draft -> confirmed -> preparing -> ready -> shipped -> delivered ->
 * closed. L'annulation est permise AVANT `shipped` uniquement : une fois la
 * marchandise partie, un `cancelled` masquerait une expedition reelle — la
 * suite se traite par les objets logistiques (shipments, exception).
 *
 * Classe PURE, testable en unite.
 */
final class TradeOrderStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['closed'],
        // Etats terminaux : aucune transition sortante.
        'closed' => [],
        'cancelled' => [],
    ];

    public function assertCanTransitionTo(TradeOrderStatus $from, TradeOrderStatus $to): void
    {
        if (! $this->canTransitionTo($from, $to)) {
            throw new InvalidTradeStateTransitionException('commande', $from->value, $to->value);
        }
    }

    public function canTransitionTo(TradeOrderStatus $from, TradeOrderStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(TradeOrderStatus $from): array
    {
        return self::ALLOWED_TRANSITIONS[$from->value];
    }
}
