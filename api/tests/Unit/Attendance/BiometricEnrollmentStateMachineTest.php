<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Modules\Attendance\Domain\Exceptions\InvalidBiometricEnrollmentTransitionException;
use App\Modules\Attendance\Domain\Support\BiometricEnrollmentStateMachine;
use PHPUnit\Framework\TestCase;

/**
 * Machine à états des enrôlements biométriques (BIO-002, #6763).
 *
 * Couvre toutes les transitions : pending→active, pending→revoked,
 * active→revoked, et les transitions illégales (réactivation, double
 * activation, révocation d'un enrôlement déjà révoqué).
 */
final class BiometricEnrollmentStateMachineTest extends TestCase
{
    private BiometricEnrollmentStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new BiometricEnrollmentStateMachine;
    }

    public function test_legal_transitions_are_accepted(): void
    {
        $this->machine->assertCanTransition(BiometricEnrollmentStatus::Pending, BiometricEnrollmentStatus::Active);
        $this->machine->assertCanTransition(BiometricEnrollmentStatus::Pending, BiometricEnrollmentStatus::Revoked);
        $this->machine->assertCanTransition(BiometricEnrollmentStatus::Active, BiometricEnrollmentStatus::Revoked);

        $this->addToAssertionCount(1);
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $illegal = [
            [BiometricEnrollmentStatus::Pending, BiometricEnrollmentStatus::Pending],
            [BiometricEnrollmentStatus::Active, BiometricEnrollmentStatus::Active],
            [BiometricEnrollmentStatus::Active, BiometricEnrollmentStatus::Pending],
            [BiometricEnrollmentStatus::Revoked, BiometricEnrollmentStatus::Active],
            [BiometricEnrollmentStatus::Revoked, BiometricEnrollmentStatus::Pending],
            [BiometricEnrollmentStatus::Revoked, BiometricEnrollmentStatus::Revoked],
        ];

        foreach ($illegal as [$from, $to]) {
            try {
                $this->machine->assertCanTransition($from, $to);
                $this->fail("Transition {$from->value}→{$to->value} aurait dû être refusée.");
            } catch (InvalidBiometricEnrollmentTransitionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_only_active_enrollment_is_usable_for_punch(): void
    {
        $this->assertTrue(BiometricEnrollmentStatus::Active->isUsable());
        $this->assertFalse(BiometricEnrollmentStatus::Pending->isUsable());
        $this->assertFalse(BiometricEnrollmentStatus::Revoked->isUsable());
    }
}
