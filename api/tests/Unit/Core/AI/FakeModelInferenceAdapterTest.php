<?php

declare(strict_types=1);

namespace Tests\Unit\Core\AI;

use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Domain\Enums\ModelType;
use App\Core\AI\Domain\Support\ModelOutputValidator;
use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Infrastructure\Adapters\FakeModelInferenceAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Contrat commun d'inférence (AI-001, #6770) — adaptateur FAKE scriptable,
 * validation par schéma des sorties avant entrée dans le domaine.
 */
final class FakeModelInferenceAdapterTest extends TestCase
{
    private FakeModelInferenceAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new FakeModelInferenceAdapter;
    }

    public function test_success_returns_schema_validated_payload_for_ocr(): void
    {
        $result = $this->adapter->infer(new ModelRequest(
            type: ModelType::OcrReading,
            correlationId: 'corr-1',
            input: ['image' => '/tmp/meter.jpg'],
        ));

        $this->assertTrue($result->isUsable());
        $this->assertSame('corr-1', $result->correlationId);
        $payload = $result->payload;
        $this->assertIsArray($payload);
        $this->assertSame('12345', $payload['value']);
        $this->assertSame('L', $payload['unit']);
        $this->assertGreaterThan(0.9, $result->confidence);
    }

    public function test_face_verification_and_liveness_use_consistent_contracts(): void
    {
        $face = $this->adapter->infer(new ModelRequest(ModelType::FaceVerification, 'corr-2', []));
        $liveness = $this->adapter->infer(new ModelRequest(ModelType::Liveness, 'corr-3', []));

        $this->assertTrue($face->isUsable());
        $this->assertTrue($liveness->isUsable());
        $facePayload = $face->payload;
        $livenessPayload = $liveness->payload;
        $this->assertIsArray($facePayload);
        $this->assertIsArray($livenessPayload);
        $this->assertTrue($facePayload['verified']);
        $this->assertTrue($livenessPayload['live']);
        $this->assertSame('face_verification', $face->type->value);
        $this->assertSame('liveness', $liveness->type->value);
    }

    public function test_programmed_failure_statuses_are_returned(): void
    {
        $this->adapter->queueStatus(ModelExecutionStatus::Rejected);
        $this->adapter->queueStatus(ModelExecutionStatus::Timeout);
        $this->adapter->queueStatus(ModelExecutionStatus::Unavailable);

        $first = $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'c1', []));
        $second = $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'c2', []));
        $third = $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'c3', []));

        $this->assertSame(ModelExecutionStatus::Rejected, $first->status);
        $this->assertSame(ModelExecutionStatus::Timeout, $second->status);
        $this->assertSame(ModelExecutionStatus::Unavailable, $third->status);
        $this->assertFalse($first->isUsable());
        $this->assertSame('FAKE_rejected', $first->reasonCode);
    }

    public function test_invalid_payload_is_rejected_by_schema_validation(): void
    {
        $this->adapter->respondWithPayload(['value' => '12345']); // unit manquant

        $this->expectExceptionMessage("is missing required key 'unit'");

        $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'corr-bad', []));
    }

    public function test_out_of_range_confidence_is_rejected(): void
    {
        $this->adapter->respondWithPayload(['value' => '1', 'unit' => 'L', 'confidence' => 1.5]);

        $this->expectExceptionMessage('confidence');

        $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'corr-conf', []));
    }

    public function test_model_versions_are_auditable(): void
    {
        $result = $this->adapter->infer(new ModelRequest(ModelType::OcrReading, 'corr-v', []));

        $this->assertSame('model-inference', $result->modelVersion->model);
        $this->assertSame('fake', $result->modelVersion->version);
        $this->assertSame('model-inference:fake', (string) $result->modelVersion);
    }

    public function test_validator_accepts_valid_payloads_per_type(): void
    {
        $validator = new ModelOutputValidator;

        $validator->validate(ModelType::FaceVerification, ['verified' => false]);
        $validator->validate(ModelType::Liveness, ['live' => true]);
        $validator->validate(ModelType::OcrReading, ['value' => '42', 'unit' => 'm3', 'confidence' => 0.8]);

        $this->addToAssertionCount(1);
    }
}
