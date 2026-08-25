<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Statut d'un relevé bancaire importé — rapprochement bancaire #5435.
 */
enum BankStatementStatus: string
{
    case Imported = 'imported';
    case Reconciling = 'reconciling';
    case Reconciled = 'reconciled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
