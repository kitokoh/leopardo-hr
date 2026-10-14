<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Mode de transport / d'expedition — BC-36 TRADE, TRADE-004/#8479.
 */
enum TradeTransportMode: string
{
    case Sea = 'sea';
    case Air = 'air';
    case Road = 'road';
    case Rail = 'rail';
    case Multimodal = 'multimodal';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
