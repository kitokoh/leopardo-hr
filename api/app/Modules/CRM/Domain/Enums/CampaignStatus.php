<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Cycle de vie d'une campagne CRM — Issue #5724.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
