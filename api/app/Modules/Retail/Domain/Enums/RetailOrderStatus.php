<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut d'une commande de vente Retail (BC-17 RETAIL, #7674).
 *
 * - `draft`     : panier en cours d'encaissement, modifiable ;
 * - `completed` : totalement payee — le stock est decremente (mouvements
 *                 `sale` via RetailStockService, seule voie d'ecriture) ;
 * - `cancelled` : annulee (une commande completee annulee genere des
 *                 mouvements `return` qui restaurent le stock).
 */
enum RetailOrderStatus: string
{
    case Draft = 'draft';

    case Completed = 'completed';

    case Cancelled = 'cancelled';
}
