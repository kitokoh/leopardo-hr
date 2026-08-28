<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Priorité d'une tâche CRM — Issue #5710 (CRM-V0-06).
 */
enum CrmTaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $priority): string => $priority->value, self::cases());
    }
}
