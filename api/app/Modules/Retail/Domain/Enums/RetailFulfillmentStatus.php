<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut logistique d'une commande en ligne Leopardo Marche
 * (BC-17 RETAIL, #7808).
 *
 * Machine d'etats du canal `online` — le statut historique
 * `RetailOrderStatus` reste la SOURCE du stock (spec §2.3) :
 * - `pending`   : commande recue, stock non touche (`status = draft`) ;
 * - `confirmed` : vendeur accepte → decrement stock via mouvements `sale`
 *                 (`status = completed`) ;
 * - `ready`     : commande preparee, prete a expedier ;
 * - `shipped`   : remise au livreur ;
 * - `delivered` : livree au client (terminal) ;
 * - `cancelled` : annulee (terminal — depuis `pending` sans mouvement,
 *                 apres confirmation avec mouvements `return`).
 *
 * Toute transition invalide est refusee (422 INVALID_TRANSITION).
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
     * La transition vers `$target` est-elle autorisee par la machine
     * d'etats ? (deny-by-default : tout couple non liste est refuse.)
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Transitions sortantes autorisees depuis l'etat courant.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Ready, self::Shipped, self::Cancelled],
            self::Ready => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }
}
