<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Enums\VerificationResult;
use App\Modules\Attendance\Domain\Support\PunchRecordingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Règle applicative unifiée de création d'un événement de présence
 * (ATT-002, #6761).
 *
 * Toutes les transitions méthode × résultat sont couvertes : méthode non
 * configurée, biométrie non enrôlée, échec de vérification, succès et
 * bascule (fallback).
 */
final class PunchRecordingPolicyTest extends TestCase
{
    public function test_configured_and_enabled_method_with_success_is_allowed(): void
    {
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['fingerprint', 'face', 'badge'],
            employeeEnabledMethods: ['fingerprint', 'face'],
        );

        $decision = $policy->decide(VerificationMethod::Fingerprint, VerificationResult::Success);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
    }

    public function test_unknown_or_unconfigured_method_is_rejected(): void
    {
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['fingerprint'],
            employeeEnabledMethods: ['fingerprint'],
        );

        foreach ([VerificationMethod::Face, VerificationMethod::Badge, VerificationMethod::Pin, VerificationMethod::Manager, VerificationMethod::Manual] as $method) {
            $decision = $policy->decide($method, VerificationResult::Success);

            $this->assertFalse($decision->allowed, "{$method->value} ne doit pas passer si non configuré");
            $this->assertSame('PUNCH_METHOD_NOT_CONFIGURED', $decision->reason);
        }
    }

    public function test_biometric_method_requires_employee_enrollment(): void
    {
        // L'employé n'a que l'empreinte d'enrôlée : le visage est refusé côté
        // serveur même si le kiosque l'envoie (BIOMETRIC_NOT_ENABLED).
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['fingerprint', 'face'],
            employeeEnabledMethods: ['fingerprint'],
        );

        $decision = $policy->decide(VerificationMethod::Face, VerificationResult::Success);

        $this->assertFalse($decision->allowed);
        $this->assertSame('BIOMETRIC_NOT_ENABLED', $decision->reason);

        // L'empreinte existante, elle, reste acceptée.
        $this->assertTrue($policy->decide(VerificationMethod::Fingerprint, VerificationResult::Success)->allowed);
    }

    public function test_non_biometric_methods_are_not_gated_by_employee_enrollment(): void
    {
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['badge', 'pin', 'manager', 'manual'],
            employeeEnabledMethods: [],
        );

        $this->assertTrue($policy->decide(VerificationMethod::Badge, VerificationResult::Success)->allowed);
        $this->assertTrue($policy->decide(VerificationMethod::Pin, VerificationResult::Success)->allowed);
        $this->assertTrue($policy->decide(VerificationMethod::Manager, VerificationResult::Success)->allowed);
        $this->assertTrue($policy->decide(VerificationMethod::Manual, VerificationResult::Success)->allowed);
    }

    /**
     * Chaque échec de vérification refuse l'enregistrement avec le code
     * machine correspondant (aucun échec facial ne crée de présence).
     */
    public function test_every_failure_result_denies_recording_with_its_code(): void
    {
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['fingerprint', 'face'],
            employeeEnabledMethods: ['fingerprint', 'face'],
        );

        $expected = [
            VerificationResult::Rejected->value => 'VERIFICATION_REJECTED',
            VerificationResult::QualityFailed->value => 'VERIFICATION_QUALITY_FAILED',
            VerificationResult::LivenessFailed->value => 'VERIFICATION_LIVENESS_FAILED',
            VerificationResult::DeviceUnreliable->value => 'VERIFICATION_DEVICE_UNRELIABLE',
            VerificationResult::Unavailable->value => 'VERIFICATION_UNAVAILABLE',
        ];

        foreach ($expected as $resultValue => $reason) {
            $result = VerificationResult::from($resultValue);
            $decision = $policy->decide(VerificationMethod::Face, $result);

            $this->assertFalse($decision->allowed, "{$resultValue} doit refuser l'enregistrement");
            $this->assertSame($reason, $decision->reason);
        }
    }

    public function test_fallback_outcome_allows_recording_when_fallback_method_is_configured(): void
    {
        // La bascule a déjà été consommée par le flux (BIO-006) : l'événement
        // est enregistré avec la méthode réellement utilisée (badge).
        $policy = new PunchRecordingPolicy(
            configuredMethods: ['face', 'badge'],
            employeeEnabledMethods: ['face'],
        );

        $decision = $policy->decide(VerificationMethod::Badge, VerificationResult::Fallback);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
    }
}
