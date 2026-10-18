<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Sens d'une OFFRE commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * `incoming` = offre RECUE (cote acheteur : reponse a une RFQ emise).
 * `outgoing` = offre EMISE (cote vendeur : reponse a une RFQ recue, ou offre
 * proactive). Une meme entreprise manipule les deux sens — c'est exactement
 * le meme objet `trade_quotes`, vu de deux cotes.
 */
enum TradeQuoteDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $direction): string => $direction->value, self::cases());
    }
}
