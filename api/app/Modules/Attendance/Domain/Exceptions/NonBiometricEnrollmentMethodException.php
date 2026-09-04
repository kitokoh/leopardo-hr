<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use DomainException;

/**
 * L'enrôlement demande une méthode non biométrique (BIO-002, #6763).
 *
 * Seuls les gabarits `fingerprint` et `face` sont stockés dans
 * `biometric_enrollments` (badge/PIN/manager/manual n'ont pas de gabarit).
 */
final class NonBiometricEnrollmentMethodException extends DomainException {}
