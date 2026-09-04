<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Support;

use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Modules\Attendance\Domain\Exceptions\InvalidBiometricEnrollmentTransitionException;

/**
 * Machine à états d'un enrôlement biométrique (BIO-002, #6763).
 *
 * Transitions autorisées :
 *
 *   pending  → active   (activation manager/RH — rend le gabarit utilisable)
 *   pending  → revoked  (rejet avant activation)
 *   active   → revoked  (remplacement, révocation RGPD, appareil compromis)
 *
 * Toute autre transition (réactivation, double activation...) est refusée.
 * Pure (aucune dépendance framework) — testée unitairement sans base.
 */
final class BiometricEnrollmentStateMachine
{
    /**
     * @return list<BiometricEnrollmentStatus> états accessibles depuis $from
     */
    public function allowedTransitions(BiometricEnrollmentStatus $from): array
    {
        return match ($from) {
            BiometricEnrollmentStatus::Pending => [
                BiometricEnrollmentStatus::Active,
                BiometricEnrollmentStatus::Revoked,
            ],
            BiometricEnrollmentStatus::Active => [
                BiometricEnrollmentStatus::Revoked,
            ],
            BiometricEnrollmentStatus::Revoked => [],
        };
    }

    /**
     * Valide une transition, lève une exception sinon.
     *
     * @throws InvalidBiometricEnrollmentTransitionException
     */
    public function assertCanTransition(
        BiometricEnrollmentStatus $from,
        BiometricEnrollmentStatus $to,
    ): void {
        if (! in_array($to, $this->allowedTransitions($from), true)) {
            throw new InvalidBiometricEnrollmentTransitionException(
                "Biometric enrollment cannot transition from '{$from->value}' to '{$to->value}'."
            );
        }
    }
}
