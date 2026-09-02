<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\Enums\VerificationResult;
use PHPUnit\Framework\TestCase;

/**
 * Résultats de vérification du pointage multi-méthodes (ATT-002, #6761).
 *
 * Les résultats couvrent : succès, rejet, qualité insuffisante, liveness
 * échoué, appareil non fiable, indisponibilité et bascule (fallback).
 */
final class VerificationResultTest extends TestCase
{
    public function test_all_required_outcomes_are_defined(): void
    {
        $values = array_map(
            static fn (VerificationResult $result): string => $result->value,
            VerificationResult::cases(),
        );

        $this->assertSame(
            [
                'success',
                'rejected',
                'quality_failed',
                'liveness_failed',
                'device_unreliable',
                'unavailable',
                'fallback',
            ],
            $values,
        );
    }

    public function test_only_success_and_fallback_allow_recording(): void
    {
        foreach (VerificationResult::cases() as $result) {
            if (in_array($result, [VerificationResult::Success, VerificationResult::Fallback], true)) {
                $this->assertTrue($result->allowsRecording(), "{$result->value} doit permettre l'enregistrement");
                $this->assertFalse($result->isRetryable(), "{$result->value} n'est pas rejouable");
            } else {
                $this->assertFalse($result->allowsRecording(), "{$result->value} ne doit pas permettre l'enregistrement");
                $this->assertTrue($result->isRetryable(), "{$result->value} est rejouable (fallback/autre méthode)");
            }
        }
    }

    public function test_failure_outcomes_carry_stable_machine_codes(): void
    {
        $this->assertSame('VERIFICATION_REJECTED', VerificationResult::Rejected->reasonCode());
        $this->assertSame('VERIFICATION_QUALITY_FAILED', VerificationResult::QualityFailed->reasonCode());
        $this->assertSame('VERIFICATION_LIVENESS_FAILED', VerificationResult::LivenessFailed->reasonCode());
        $this->assertSame('VERIFICATION_DEVICE_UNRELIABLE', VerificationResult::DeviceUnreliable->reasonCode());
        $this->assertSame('VERIFICATION_UNAVAILABLE', VerificationResult::Unavailable->reasonCode());
        $this->assertSame('VERIFICATION_SUCCESS', VerificationResult::Success->reasonCode());
        $this->assertSame('VERIFICATION_FALLBACK_USED', VerificationResult::Fallback->reasonCode());
    }
}
