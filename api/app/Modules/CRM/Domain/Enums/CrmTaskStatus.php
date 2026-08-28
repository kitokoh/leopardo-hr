<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Statuts bornés d'une tâche CRM — Issue #5710 (CRM-V0-06).
 *
 * Le cycle est volontairement simple : `todo` → `in_progress` → `done`
 * (ou `cancelled`). `done` horodate `completed_at` ; une tâche `done` ou
 * `cancelled` ne peut pas revenir en arrière via l'API (garde applicative).
 */
enum CrmTaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
