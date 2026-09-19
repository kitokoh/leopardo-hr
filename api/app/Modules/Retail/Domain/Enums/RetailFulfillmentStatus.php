<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Cycle de vie d'une commande EN LIGNE du module Retail (BC-17, #7808).
 *
 * Machine d'etats (spec MARKETPLACE_RETAIL_PUBLIC §3.3) :
 *
 *   pending -> confirmed -> ready -> shipped -> delivered
 *      \           \          \
 *       +-----------+----------+--> cancelled
 *
 * - `confirm` decremente le stock (mouvements `sale` via RetailStockService) ;
 * - `cancel` restaure le stock (mouvements `return`) si la commande etait
 *   confirmee (confirmed/ready) ;
 * - toute transition hors du graphe => 422 `INVALID_TRANSITION`.
 *
 * NULL en base pour les commandes POS (#7674), qui gardent leur cycle
 * draft/completed/cancelled.
 */
enum RetailFulfillmentStatus: string
{
    case Pending = 'pending';

    case Confirmed = 'confirmed';

    case Ready = 'ready';

    case Shipped = 'shipped';

    case Delivered = 'delivered';

    case Cancelled = 'cancelled';

    /**
     * Cibles atteignables depuis l'etat courant.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Ready, self::Cancelled],
            self::Ready => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Le stock a-t-il deja ete decremente dans cet etat ? (confirm et
     * suivants, avant expedition — une annulation doit alors le restaurer).
     */
    public function stockCommitted(): bool
    {
        return $this === self::Confirmed || $this === self::Ready;
    }
}
