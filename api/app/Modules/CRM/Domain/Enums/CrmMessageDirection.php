<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Direction d'un message de canal CRM (issue #5725).
 */
final class CrmMessageDirection
{
    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::OUTBOUND, self::INBOUND];
    }
}
