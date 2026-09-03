<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Exceptions;

use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use RuntimeException;

/**
 * Transition de statut illégale (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * Lever par DeliveryStateMachine lorsqu'une transition viole un invariant :
 * état terminal réouvert, saut d'étape, ou `delivered` sans preuve (POD).
 */
final class InvalidDeliveryTransitionException extends RuntimeException
{
    public static function notAllowed(DeliveryStatus $from, DeliveryStatus $to): self
    {
        return new self(sprintf(
            'Invalid delivery transition: "%s" → "%s" is not allowed.',
            $from->value,
            $to->value,
        ));
    }

    public static function proofRequired(DeliveryStatus $to): self
    {
        return new self(sprintf(
            'Transition to "%s" requires a proof of delivery (POD).',
            $to->value,
        ));
    }
}
