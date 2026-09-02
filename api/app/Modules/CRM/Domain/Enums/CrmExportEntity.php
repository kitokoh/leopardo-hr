<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Entités exportables du CRM client tenant (issue #5729).
 *
 * Whitelist stricte — toute valeur inconnue est rejetée par la validation.
 */
final class CrmExportEntity
{
    public const ACCOUNTS = 'accounts';

    public const CONTACTS = 'contacts';

    public const LEADS = 'leads';

    public const OPPORTUNITIES = 'opportunities';

    public const ACTIVITIES = 'activities';

    public const TASKS = 'tasks';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::ACCOUNTS, self::CONTACTS, self::LEADS, self::OPPORTUNITIES, self::ACTIVITIES, self::TASKS];
    }

    public static function isValid(string $entity): bool
    {
        return in_array($entity, self::values(), true);
    }
}
