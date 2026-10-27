<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HOSP-005 (#7947) — transition de bail interdite par la machine à états
 * (ex. résiliation d'un bail déjà terminé).
 */
class HospitalityInvalidLeaseTransitionException extends DomainException
{
    public function __construct(string $from, string $target)
    {
        parent::__construct(
            (string) __('hospitality.invalid_lease_transition', ['from' => $from, 'target' => $target]),
            409,
            'INVALID_LEASE_TRANSITION'
        );
    }
}
