<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Statut d'un envoi unitaire de campagne — Issue #5724.
 */
enum CampaignSendStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Cancelled = 'cancelled';
    case Suppressed = 'suppressed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
