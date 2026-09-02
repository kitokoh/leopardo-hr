<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Opérateurs de la grammaire de segment CRM — Issue #5723.
 *
 * Sous-ensemble strict, sans SQL utilisateur : seuls ces opérateurs sont
 * acceptés, par champ allowlisté (voir SegmentDefinitionValidator).
 */
enum SegmentOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case In = 'in';
    case Contains = 'contains';
    case Gte = 'gte';
    case Lte = 'lte';
    case Between = 'between';
    case IsNull = 'is_null';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $op): string => $op->value, self::cases());
    }
}
