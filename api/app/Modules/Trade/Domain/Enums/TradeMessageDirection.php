<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Direction d'un message de dossier — TRADE-005/#8480.
 *
 * `internal` = note interne (personne ne la voit hors du tenant).
 * `outbound` = message destine a la contrepartie. `inbound` = message recu
 * (importe ou saisi). Toute interaction reste rattachee au DOSSIER.
 */
enum TradeMessageDirection: string
{
    case Internal = 'internal';
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $direction): string => $direction->value, self::cases());
    }
}
