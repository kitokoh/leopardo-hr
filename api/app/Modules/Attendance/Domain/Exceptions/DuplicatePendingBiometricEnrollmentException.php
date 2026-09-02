<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un enrôlement pending existe déjà pour cet employé et cette méthode
 * (BIO-002, #6763).
 *
 * L'API répond `ENROLLMENT_ALREADY_PENDING` (409) : l'interface propose de
 * consulter l'état de l'enrôlement en attente au lieu de dupliquer. Étend
 * `App\Exceptions\DomainException` (renderer JSON dédié, lot 6 ATT-004).
 */
final class DuplicatePendingBiometricEnrollmentException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'ENROLLMENT_ALREADY_PENDING',
            409,
            'ENROLLMENT_ALREADY_PENDING'
        );
    }
}
