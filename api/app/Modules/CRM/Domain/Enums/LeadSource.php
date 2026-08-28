<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Origine d'acquisition d'un lead CRM client — Issue #5709 (CRM-V0-05).
 *
 * Permet le pilotage par canal (dashboard pipeline, issue #5721) et reste
 * une chaîne courte contrôlée par allowlist (CHECK SQL + validation stricte
 * CRM-V0-07, issue #5711).
 */
enum LeadSource: string
{
    case Manual = 'manual';
    case Referral = 'referral';
    case Website = 'website';
    case Social = 'social';
    case Email = 'email';
    case Call = 'call';
    case Event = 'event';
    case Partner = 'partner';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
