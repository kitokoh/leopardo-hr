<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Statuts d'un lead CRM client — Issue #5709 (CRM-V0-05).
 *
 * Le funnel commercial tenant : un lead démarre en `new` et progresse vers
 * `qualified`/`proposal` avant conversion (`won`). `lost` et `junk` sont des
 * états terminaux. Le passage en `won` déclenche la conversion en
 * opportunity/account (CRM-V0-06, issue #5717).
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';
    case Junk = 'junk';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
