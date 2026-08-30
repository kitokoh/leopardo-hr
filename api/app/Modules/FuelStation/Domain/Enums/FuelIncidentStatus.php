<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5804 — Statuts d'un incident équipement (FUEL-010).
 *
 * Machine à états : open → assigned → in_progress → resolved | cancelled
 * (open → in_progress possible sans assignation préalable ; resolved/cancelled
 * terminaux).
 */
enum FuelIncidentStatus: string
{
    case Open = 'open';

    case Assigned = 'assigned';

    case InProgress = 'in_progress';

    case Resolved = 'resolved';

    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function terminal(): array
    {
        return [self::Resolved->value, self::Cancelled->value];
    }
}
