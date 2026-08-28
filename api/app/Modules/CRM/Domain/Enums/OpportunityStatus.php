<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Statuts d'une opportunité CRM client — Issue #5709 (CRM-V0-05).
 *
 * Le détail du funnel est porté par le stage du pipeline (`crm_pipeline_stages`) ;
 * le statut reste volontairement ternaire pour piloter le taux de
 * conversion global (won/lost) sans dupliquer la logique des stages.
 */
enum OpportunityStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
