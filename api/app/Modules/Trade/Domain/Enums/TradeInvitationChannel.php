<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Invitation d'une RFQ a un fournisseur : canal et etat — TRADE-003/#8478.
 */
enum TradeInvitationChannel: string
{
    case InternalNote = 'internal_note';
    case Email = 'email';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }
}
