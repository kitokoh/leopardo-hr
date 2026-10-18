<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut d'une cotation transport recue d'un prestataire — TRADE-006/#8481.
 */
enum TradeTransportQuoteStatus: string
{
    case Received = 'received';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Expired], true);
    }
}
