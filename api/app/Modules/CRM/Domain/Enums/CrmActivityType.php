<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Type d'une activité CRM (timeline append-only) — Issue #5710 (CRM-V0-06).
 */
enum CrmActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
