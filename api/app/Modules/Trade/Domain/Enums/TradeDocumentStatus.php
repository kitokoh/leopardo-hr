<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut d'un document — BC-36 TRADE, TRADE-005/#8480.
 */
enum TradeDocumentStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Signed = 'signed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
