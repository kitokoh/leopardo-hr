<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Canal d'origine d'une commande Retail (BC-17 RETAIL, #7674).
 *
 * - `pos`    : vente en caisse (POS v1, #7674) ;
 * - `online` : reserve a la future boutique e-commerce (BC-17 phase
 *              suivante) — valeur acceptee des maintenant, aucun flux.
 */
enum RetailOrderSource: string
{
    case Pos = 'pos';

    case Online = 'online';
}
