<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Exceptions;

use App\Exceptions\DomainException;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;

/**
 * Transition de course invalide (BC-34 VTC, VTC-03/#8359).
 *
 * Levée par VtcRideStateMachine quand une transition sort du cycle de vie
 * autorisé (spec §5.2) — 409 fail-closed, code stable
 * VTC_INVALID_RIDE_TRANSITION. Même pattern que BC-26
 * (InvalidDeliveryTransitionException).
 */
final class InvalidRideTransitionException extends DomainException
{
    public function __construct(VtcRideStatus $from, VtcRideStatus $to)
    {
        parent::__construct(
            "Transition de course VTC invalide : {$from->value} → {$to->value}.",
            409,
            'VTC_INVALID_RIDE_TRANSITION'
        );
    }
}
