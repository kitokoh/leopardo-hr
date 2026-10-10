<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Enums;

use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;

/**
 * Statut d'une course VTC (BC-34 VTC, VTC-02/#8358).
 *
 * Cycle de vie (spec §5.2) : requested → dispatching → accepted → arrived →
 * in_progress → completed ; expired (aucun chauffeur dans la fenêtre) et
 * cancelled (motif tracé) depuis tout état amont. États terminaux :
 * completed, expired, cancelled — aucune réouverture. Les transitions sont
 * verrouillées par VtcRideStateMachine (VTC-04).
 *
 * @see VtcRideStateMachine
 */
enum VtcRideStatus: string
{
    case Requested = 'requested';
    case Dispatching = 'dispatching';
    case Accepted = 'accepted';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
