<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut d'une demande de cotation transport — TRADE-006/#8481.
 */
enum TradeTransportRequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Quoted = 'quoted';
    case Selected = 'selected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Selected, self::Expired, self::Cancelled], true);
    }
}
