<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Sens d'une COMMANDE — BC-36 TRADE, TRADE-004/#8479.
 *
 * `purchase` = commande d'achat (on achete au fournisseur).
 * `sale` = commande de vente (on vend au client). Meme objet, deux sens.
 */
enum TradeOrderDirection: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $direction): string => $direction->value, self::cases());
    }
}
