<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'une commande restaurant (BC-25, RESTO-214, issue #6179).
 */
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case IN_PREPARATION = 'in_preparation';
    case READY = 'ready';
    case SERVED = 'served';
    case PAID = 'paid';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::OPEN => 'Ouverte',
            self::IN_PREPARATION => 'En préparation',
            self::READY => 'Prête',
            self::SERVED => 'Servie',
            self::PAID => 'Payée',
            self::CLOSED => 'Clôturée',
            self::CANCELLED => 'Annulée',
            self::REFUNDED => 'Remboursée',
        };
    }
}
