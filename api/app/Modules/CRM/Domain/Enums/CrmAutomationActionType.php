<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Actions autorisées dans les automatisations CRM (issue #5728).
 *
 * Whitelist stricte : le moteur ne sait exécuter QUE ces types. Les actions
 * sont terminales (aucune action "dispatch_event") → aucune boucle possible
 * entre automatisations.
 */
final class CrmAutomationActionType
{
    public const SEND_WHATSAPP = 'send_whatsapp';

    public const SEND_SMS = 'send_sms';

    public const SEND_EMAIL = 'send_email';

    public const CREATE_TASK = 'create_task';

    public const HTTP_WEBHOOK = 'http_webhook';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::SEND_WHATSAPP, self::SEND_SMS, self::SEND_EMAIL, self::CREATE_TASK, self::HTTP_WEBHOOK];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::values(), true);
    }
}
