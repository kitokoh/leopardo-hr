<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Types de canaux de communication CRM (issue #5725/#5727).
 *
 * Whitelist stricte : toute valeur inconnue est rejetée par la validation
 * (Rule\In) — jamais de string libre en base.
 */
final class CrmChannelType
{
    public const WHATSAPP = 'whatsapp';

    public const SMS = 'sms';

    public const EMAIL = 'email';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::WHATSAPP, self::SMS, self::EMAIL];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::values(), true);
    }
}
