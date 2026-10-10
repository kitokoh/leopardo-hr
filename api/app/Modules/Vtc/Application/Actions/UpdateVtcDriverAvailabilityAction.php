<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Exceptions\DomainException;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;

/**
 * Bascule de disponibilité d'un chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 *   - available=true  : offline → available (le dispatch VTC-04 peut
 *     solliciter) ; interdit depuis `suspended` (décision exploitant) ou
 *     `busy` (course en cours — la clôture libère automatiquement) ;
 *   - available=false : → offline ; interdit depuis `busy` (on ne disparaît
 *     pas du dispatch en pleine course) et `suspended`.
 */
final class UpdateVtcDriverAvailabilityAction
{
    /**
     * @throws DomainException (409 VTC_AVAILABILITY_LOCKED)
     */
    public function execute(VtcDriver $driver, bool $available): VtcDriver
    {
        if (in_array($driver->status, [VtcDriverStatus::Busy, VtcDriverStatus::Suspended], true)) {
            throw new DomainException(
                (string) __('vtc.availability_locked', ['status' => $driver->status->value]),
                409,
                'VTC_AVAILABILITY_LOCKED'
            );
        }

        $driver->forceFill([
            'status' => $available
                ? VtcDriverStatus::Available->value
                : VtcDriverStatus::Offline->value,
        ])->save();

        return $driver->refresh();
    }
}
