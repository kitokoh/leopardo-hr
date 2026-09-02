<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Transition d'enrôlement biométrique illégale (BIO-002, #6763).
 *
 * Toute transition non couverte par la machine à états
 * (BiometricEnrollmentStateMachine) est refusée — un enrôlement ne peut pas
 * être réactivé après révocation, ni activé deux fois. Réponse API :
 * `INVALID_ENROLLMENT_TRANSITION` (422).
 */
final class InvalidBiometricEnrollmentTransitionException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'INVALID_ENROLLMENT_TRANSITION',
            422,
            'INVALID_ENROLLMENT_TRANSITION'
        );
    }
}
