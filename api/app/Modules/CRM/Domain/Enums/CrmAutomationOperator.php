<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Opérateurs de conditions d'automatisation CRM (issue #5728).
 */
final class CrmAutomationOperator
{
    public const EQUALS = 'equals';

    public const NOT_EQUALS = 'not_equals';

    public const CONTAINS = 'contains';

    public const EXISTS = 'exists';

    public const GREATER_THAN = 'greater_than';

    public const LESS_THAN = 'less_than';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::EQUALS, self::NOT_EQUALS, self::CONTAINS, self::EXISTS, self::GREATER_THAN, self::LESS_THAN];
    }

    public static function isValid(string $operator): bool
    {
        return in_array($operator, self::values(), true);
    }
}
