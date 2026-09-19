<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Codes de mouvement de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * - `purchase`     : reception d'achat fournisseur (entree) ;
 * - `sale`         : vente au client (sortie) ;
 * - `adjustment`   : ajustement d'inventaire (signe libre) ;
 * - `return`       : retour client (entree) ;
 * - `transfer_in`  : transfert entrant depuis un autre emplacement ;
 * - `transfer_out` : transfert sortant vers un autre emplacement ;
 * - `loss`         : perte / casse / vol (sortie).
 *
 * Le code est stocke en string en base (colonne `reason_code`) ; l'enum PHP
 * est la source de verite cote code (pattern StockMovementReason #6170).
 */
enum RetailStockReasonCode: string
{
    case Purchase = 'purchase';

    case Sale = 'sale';

    case Adjustment = 'adjustment';

    case Return = 'return';

    case TransferIn = 'transfer_in';

    case TransferOut = 'transfer_out';

    case Loss = 'loss';
}
