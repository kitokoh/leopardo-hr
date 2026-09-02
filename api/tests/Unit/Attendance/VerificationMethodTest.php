<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use PHPUnit\Framework\TestCase;

/**
 * Méthodes de vérification du pointage multi-méthodes (ATT-002, #6761).
 *
 * Couvre la compatibilité avec l'empreinte existante (valeurs persistées de
 * `attendance_logs.method`) et le rejet des méthodes inconnues.
 */
final class VerificationMethodTest extends TestCase
{
    public function test_six_initial_methods_are_defined(): void
    {
        $values = array_map(
            static fn (VerificationMethod $method): string => $method->value,
            VerificationMethod::cases(),
        );

        $this->assertSame(
            ['fingerprint', 'face', 'badge', 'pin', 'manager', 'manual'],
            $values,
        );
    }

    public function test_badge_is_persisted_as_legacy_card_value(): void
    {
        $this->assertSame('card', VerificationMethod::Badge->attendanceLogMethod());
        $this->assertSame('fingerprint', VerificationMethod::Fingerprint->attendanceLogMethod());
        $this->assertSame('face', VerificationMethod::Face->attendanceLogMethod());
    }

    public function test_existing_fingerprint_flow_remains_compatible(): void
    {
        // Valeurs persistées par la sync ZKTeco (#5121) → domaine.
        $this->assertSame(VerificationMethod::Fingerprint, VerificationMethod::fromAttendanceLogMethod('fingerprint'));
        $this->assertSame(VerificationMethod::Face, VerificationMethod::fromAttendanceLogMethod('face'));
        $this->assertSame(VerificationMethod::Badge, VerificationMethod::fromAttendanceLogMethod('card'));
    }

    public function test_unknown_or_ambiguous_methods_are_rejected(): void
    {
        // `mobile`, `qr`, `biometric`, `geo_auto`, `zkteco` ne sont pas des
        // méthodes de vérification d'identité : aucune extension silencieuse.
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('mobile'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('qr'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('biometric'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('geo_auto'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('zkteco'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod('retina-scan'));
        $this->assertNull(VerificationMethod::fromAttendanceLogMethod(''));
    }

    public function test_biometric_classification(): void
    {
        $this->assertTrue(VerificationMethod::Fingerprint->isBiometric());
        $this->assertTrue(VerificationMethod::Face->isBiometric());
        $this->assertFalse(VerificationMethod::Badge->isBiometric());
        $this->assertFalse(VerificationMethod::Pin->isBiometric());
        $this->assertFalse(VerificationMethod::Manager->isBiometric());
        $this->assertFalse(VerificationMethod::Manual->isBiometric());
    }
}
