<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * L'enrôlement demande une méthode non biométrique (BIO-002, #6763).
 *
 * Seuls les gabarits `fingerprint` et `face` sont stockés dans
 * `biometric_enrollments` (badge/PIN/manager/manual n'ont pas de gabarit).
 * Réponse API : `NON_BIOMETRIC_ENROLLMENT_METHOD` (422).
 */
final class NonBiometricEnrollmentMethodException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'NON_BIOMETRIC_ENROLLMENT_METHOD',
            422,
            'NON_BIOMETRIC_ENROLLMENT_METHOD'
        );
    }
}
