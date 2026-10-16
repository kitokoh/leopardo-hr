<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Support;

use App\Modules\Trade\Domain\Enums\TradeShipmentStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;

/**
 * Machine a etats d'une expedition — BC-36 TRADE, TRADE-004/#8479.
 *
 * Cycle : pending -> booked -> in_transit -> arrived -> in_customs ->
 * delivered. `exception` est atteignable depuis tout etat non terminal
 * (incident logistique) et `delivered` / `exception` sont terminaux.
 *
 * Classe PURE, testable en unite.
 */
final class TradeShipmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['booked', 'in_transit', 'exception'],
        'booked' => ['in_transit', 'arrived', 'exception'],
        'in_transit' => ['arrived', 'in_customs', 'exception'],
        'arrived' => ['in_customs', 'delivered', 'exception'],
        'in_customs' => ['delivered', 'exception'],
        // Etats terminaux : aucune transition sortante.
        'delivered' => [],
        'exception' => [],
    ];

    public function assertCanTransitionTo(TradeShipmentStatus $from, TradeShipmentStatus $to): void
    {
        if (! $this->canTransitionTo($from, $to)) {
            throw new InvalidTradeStateTransitionException('expedition', $from->value, $to->value);
        }
    }

    public function canTransitionTo(TradeShipmentStatus $from, TradeShipmentStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(TradeShipmentStatus $from): array
    {
        return self::ALLOWED_TRANSITIONS[$from->value];
    }
}
