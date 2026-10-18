<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut d'un cas d'aide au dedouanement — TRADE-007/#8482.
 *
 * `incomplete` : il manque des informations (liste dans `missing_info`).
 * `ready` : le dossier est documentairement pret — ce qui ne signifie JAMAIS
 * que la douane a valide quoi que ce soit : aucune estimation Leopardo ne
 * constitue une decision officielle ni une garantie de conformite.
 */
enum TradeCustomsCaseStatus: string
{
    case Incomplete = 'incomplete';
    case Ready = 'ready';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
