<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut d'un dossier de transaction — BC-36 TRADE, TRADE-005/#8480.
 */
enum TradeDealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return $this !== self::Open;
    }
}
