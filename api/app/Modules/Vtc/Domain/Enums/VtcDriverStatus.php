<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Enums;

/**
 * Statut d'un chauffeur VTC (BC-34 VTC, VTC-02/#8358).
 *
 *   - offline    : hors service — jamais éligible au dispatch ;
 *   - available  : en service et libre — SEUL statut éligible au matching
 *                  du dispatch (VTC-04) ;
 *   - busy       : sur une course — temporairement hors matching ;
 *   - suspended  : suspendu par l'exploitant — jamais éligible.
 */
enum VtcDriverStatus: string
{
    case Offline = 'offline';
    case Available = 'available';
    case Busy = 'busy';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
