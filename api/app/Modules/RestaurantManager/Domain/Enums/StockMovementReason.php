<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Motif d'un mouvement de stock (BC-25, RESTO-214, issue #6179).
 */
enum StockMovementReason: string
{
    case SALE = 'sale';
    case RECEIVING = 'receiving';
    case COUNT = 'count';
    case ADJUSTMENT = 'adjustment';
    case WASTE = 'waste';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::SALE => 'Vente',
            self::RECEIVING => 'Réception',
            self::COUNT => 'Inventaire',
            self::ADJUSTMENT => 'Ajustement',
            self::WASTE => 'Perte',
            self::TRANSFER => 'Transfert',
        };
    }
}
