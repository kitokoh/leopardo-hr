<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

use App\Modules\Trade\Domain\Support\TradeRfqStateMachine;

/**
 * Statut d'une demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Cycle de vie : draft → open → (awarded | closed | cancelled).
 * Etats terminaux : awarded, closed, cancelled — aucune reouverture.
 * Les transitions sont verrouillees par {@see TradeRfqStateMachine}.
 */
enum TradeRfqStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Awarded, self::Closed, self::Cancelled], true);
    }

    public function allowsInvitations(): bool
    {
        return $this === self::Open;
    }
}
