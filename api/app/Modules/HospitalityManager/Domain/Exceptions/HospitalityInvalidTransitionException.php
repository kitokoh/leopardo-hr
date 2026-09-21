<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HOSP-004 (#7946) — transition de réservation interdite par la machine à
 * états (ex. check-in sur une réservation annulée).
 */
class HospitalityInvalidTransitionException extends DomainException
{
    public function __construct(string $from, string $target)
    {
        parent::__construct(
            (string) __('hospitality.invalid_transition', ['from' => $from, 'target' => $target]),
            409,
            'INVALID_RESERVATION_TRANSITION'
        );
    }
}
