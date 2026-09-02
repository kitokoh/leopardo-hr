<?php

declare(strict_types=1);

namespace Tests\Unit\Core\AI;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Domain\ValueObjects\FaceVerificationRequest;
use App\Core\AI\Infrastructure\Adapters\FakeFaceVerificationAdapter;
use App\Core\AI\Infrastructure\Adapters\UnavailableFaceVerificationAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Vérification faciale (BIO-001, #6762) — port neutre, adaptateur testable
 * et défaut fail-closed. Scénarios : verified, rejected, liveness_failed,
 * quality_failed, provider_unavailable.
 */
final class FaceVerificationAdapterTest extends TestCase
{
    private function request(string $correlationId = 'corr-1'): FaceVerificationRequest
    {
        return new FaceVerificationRequest(
            correlationId: $correlationId,
            templateReference: 'tmpl:face:42:v3',
            captureReference: 'captures/tenant-1/kiosk-7/2026-09-02/abc.jpg',
        );
    }

    public function test_unconfigured_provider_is_fail_closed(): void
    {
        $adapter = new UnavailableFaceVerificationAdapter;

        $result = $adapter->verify($this->request());

        $this->assertSame(FaceVerificationStatus::ProviderUnavailable, $result->status);
        $this->assertSame('FACE_PROVIDER_NOT_CONFIGURED', $result->reasonCode);
        $this->assertFalse($result->isVerified());
        $this->assertSame('unconfigured', $result->modelVersion->version);
    }

    public function test_fake_adapter_verifies_by_default(): void
    {
        $adapter = new FakeFaceVerificationAdapter;

        $result = $adapter->verify($this->request());

        $this->assertSame(FaceVerificationStatus::Verified, $result->status);
        $this->assertTrue($result->isVerified());
        $this->assertGreaterThan(0.9, $result->confidence);
        $this->assertSame('corr-1', $result->correlationId);
    }

    public function test_all_required_scenarios_are_scriptable(): void
    {
        $adapter = new FakeFaceVerificationAdapter;

        $scenarios = [
            FaceVerificationStatus::Rejected,
            FaceVerificationStatus::LivenessFailed,
            FaceVerificationStatus::QualityFailed,
            FaceVerificationStatus::ProviderUnavailable,
        ];

        foreach ($scenarios as $status) {
            $adapter->queueStatus($status);
        }

        foreach ($scenarios as $expected) {
            $result = $adapter->verify($this->request('corr-'.$expected->value));
            $this->assertSame($expected, $result->status, $expected->value);
            $this->assertFalse($result->isVerified());
            $this->assertSame('FAKE_'.$expected->value, $result->reasonCode);
        }
    }

    public function test_engine_can_be_replaced_by_configuration(): void
    {
        // La résolution du port se fait par configuration
        // (ai.models.face_verification.adapter) — voir AttendanceServiceProvider.
        // Le FAKE implémente le même contrat que l'adaptateur réel : le
        // remplacement ne touche aucun agrégat métier.
        $this->assertInstanceOf(FaceVerificationPort::class, new FakeFaceVerificationAdapter);
        $this->assertInstanceOf(FaceVerificationPort::class, new UnavailableFaceVerificationAdapter);
    }

    public function test_verification_requires_template_and_capture_references(): void
    {
        $adapter = new FakeFaceVerificationAdapter;

        $this->expectException(InvalidArgumentException::class);

        $adapter->verify(new FaceVerificationRequest(
            correlationId: 'corr-empty',
            templateReference: '',
            captureReference: '',
        ));
    }
}
