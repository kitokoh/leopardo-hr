<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

use App\Modules\Trade\Domain\Support\TradeOrderStateMachine;

/**
 * Statut d'une commande — BC-36 TRADE, TRADE-004/#8479.
 *
 * Cycle : draft → confirmed → preparing → ready → shipped → delivered →
 * closed. L'annulation est permise AVANT l'expedition (`shipped`) : une fois
 * la marchandise partie, l'arret passe par les objets logistiques, jamais par
 * un `cancelled` qui masquerait une expedition reelle.
 *
 * Transitions verrouillees par {@see TradeOrderStateMachine}.
 */
enum TradeOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Confirmed,
            self::Preparing,
            self::Ready,
        ], true);
    }

    /**
     * Une expedition ne se cree que sur une commande en cours d'execution.
     */
    public function allowsShipment(): bool
    {
        return in_array($this, [self::Confirmed, self::Preparing, self::Ready, self::Shipped], true);
    }
}
