<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Domain\Enums\ModelType;
use App\Core\AI\Domain\Support\ModelOutputValidator;
use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Domain\ValueObjects\ModelResult;
use App\Core\AI\Domain\ValueObjects\ModelVersion;

/**
 * Adaptateur FAKE scriptable pour les tests (AI-001, #6770).
 *
 * Retourne un payload validé par schéma (ModelOutputValidator) ou un statut
 * d'échec programmé (FIFO). Utilisé par les tests de domaine et d'intégration
 * (OCR FuelStation AI-002, vérification faciale) — aucun fournisseur réel.
 */
final class FakeModelInferenceAdapter implements ModelInferencePort
{
    /** @var list<ModelExecutionStatus> */
    private array $statusQueue = [];

    /** @var array<string, mixed>|null */
    private ?array $nextPayload = null;

    private ModelExecutionStatus $defaultStatus = ModelExecutionStatus::Succeeded;

    public function __construct(
        private readonly ModelOutputValidator $validator = new ModelOutputValidator,
        private readonly ModelVersion $modelVersion = new ModelVersion('model-inference', 'fake'),
    ) {}

    /** Programme le prochain statut retourné (FIFO). */
    public function queueStatus(ModelExecutionStatus $status): void
    {
        $this->statusQueue[] = $status;
    }

    public function setDefaultStatus(ModelExecutionStatus $status): void
    {
        $this->defaultStatus = $status;
    }

    /**
     * Programme le payload du prochain appel réussi.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function respondWithPayload(?array $payload): void
    {
        $this->nextPayload = $payload;
    }

    public function infer(ModelRequest $request): ModelResult
    {
        $status = $this->defaultStatus;
        if ($this->statusQueue !== []) {
            // array_shift sur une liste non vide renvoie toujours un élément.
            $status = array_shift($this->statusQueue);
        }

        if ($status !== ModelExecutionStatus::Succeeded) {
            return new ModelResult(
                status: $status,
                type: $request->type,
                correlationId: $request->correlationId,
                modelVersion: $this->modelVersion,
                reasonCode: 'FAKE_'.$status->value,
            );
        }

        $payload = $this->nextPayload ?? $this->defaultPayloadFor($request);
        $this->nextPayload = null;

        $this->validator->validate($request->type, $payload);

        $confidence = is_numeric($payload['confidence'] ?? null) ? (float) $payload['confidence'] : 0.0;

        return new ModelResult(
            status: ModelExecutionStatus::Succeeded,
            type: $request->type,
            correlationId: $request->correlationId,
            modelVersion: $this->modelVersion,
            confidence: $confidence,
            payload: $payload,
        );
    }

    /**
     * Payload minimal valide par type (défaut si aucun payload programmé).
     *
     * @return array<string, mixed>
     */
    private function defaultPayloadFor(ModelRequest $request): array
    {
        return match ($request->type) {
            ModelType::FaceVerification => ['verified' => true, 'confidence' => 0.98],
            ModelType::Liveness => ['live' => true, 'confidence' => 0.99],
            ModelType::OcrReading => ['value' => '12345', 'unit' => 'L', 'confidence' => 0.95],
        };
    }
}
