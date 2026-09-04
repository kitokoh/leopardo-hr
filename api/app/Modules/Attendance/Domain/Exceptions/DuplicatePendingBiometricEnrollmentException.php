<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use DomainException;

/**
 * Un enrôlement pending existe déjà pour cet employé et cette méthode
 * (BIO-002, #6763).
 *
 * L'API répond `ENROLLMENT_ALREADY_PENDING` : l'interface propose de
 * consulter l'état de l'enrôlement en attente au lieu de dupliquer.
 */
final class DuplicatePendingBiometricEnrollmentException extends DomainException {}
