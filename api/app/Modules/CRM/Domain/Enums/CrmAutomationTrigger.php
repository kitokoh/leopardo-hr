<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Événements déclencheurs d'automatisations CRM (issue #5728).
 *
 * Whitelist stricte : un événement inconnu est refusé à l'écriture. Les
 * événements `crm.message.*` sont émis par le module CRM (CrmChannelService) ;
 * les événements `crm.*.created` / `crm.opportunity.stage_changed` seront
 * émis par le socle V0 une fois mergé (contrat documenté).
 */
final class CrmAutomationTrigger
{
    public const LEAD_CREATED = 'crm.lead.created';

    public const CONTACT_CREATED = 'crm.contact.created';

    public const ACCOUNT_CREATED = 'crm.account.created';

    public const OPPORTUNITY_STAGE_CHANGED = 'crm.opportunity.stage_changed';

    public const MESSAGE_INBOUND = 'crm.message.inbound';

    public const MESSAGE_SENT = 'crm.message.sent';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [
            self::LEAD_CREATED,
            self::CONTACT_CREATED,
            self::ACCOUNT_CREATED,
            self::OPPORTUNITY_STAGE_CHANGED,
            self::MESSAGE_INBOUND,
            self::MESSAGE_SENT,
        ];
    }

    public static function isValid(string $event): bool
    {
        return in_array($event, self::values(), true);
    }
}
