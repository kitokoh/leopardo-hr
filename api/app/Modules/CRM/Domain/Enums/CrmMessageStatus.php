<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Statuts de cycle de vie d'un message de canal CRM (issue #5725).
 *
 * `dead_lettered` est l'état terminal après épuisement des tentatives
 * (429/5xx fournisseur) — jamais de retry infini (critère #5725).
 */
final class CrmMessageStatus
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const DEAD_LETTERED = 'dead_lettered';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::QUEUED, self::SENT, self::DELIVERED, self::READ, self::FAILED, self::DEAD_LETTERED];
    }
}
