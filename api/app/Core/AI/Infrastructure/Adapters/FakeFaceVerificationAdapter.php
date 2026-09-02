<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Domain\ValueObjects\FaceVerificationRequest;
use App\Core\AI\Domain\ValueObjects\FaceVerificationResult;
use App\Core\AI\Domain\ValueObjects\ModelVersion;
use InvalidArgumentException;

/**
 * Adaptateur FAKE scriptable pour les tests (BIO-001, #6762).
 *
 * Scénarios couverts : `verified`, `rejected`, `liveness_failed`,
 * `quality_failed`, `provider_unavailable`. Le comportement est piloté par
 * file de statuts (FIFO) ; sans file, le statut par défaut est retourné.
 *
 * Aucun appel facial réel : les références ne sont jamais lues, mais doivent
 * être présentes (contrat : appel seulement avec tenant/appareil
 * authentifiés et capture/gabarit référencés).
 */
final class FakeFaceVerificationAdapter implements FaceVerificationPort
{
    /** @var list<FaceVerificationStatus> */
    private array $statusQueue = [];

    private FaceVerificationStatus $defaultStatus = FaceVerificationStatus::Verified;

    public function __construct(
        private readonly ModelVersion $modelVersion = new ModelVersion('face-verifier', 'fake'),
    ) {}

    /** Programme le prochain statut retourné (FIFO). */
    public function queueStatus(FaceVerificationStatus $status): void
    {
        $this->statusQueue[] = $status;
    }

    public function setDefaultStatus(FaceVerificationStatus $status): void
    {
        $this->defaultStatus = $status;
    }

    public function verify(FaceVerificationRequest $request): FaceVerificationResult
    {
        if ($request->templateReference === '' || $request->captureReference === '') {
            throw new InvalidArgumentException('Face verification requires both template and capture references.');
        }

        $status = $this->defaultStatus;
        if ($this->statusQueue !== []) {
            // array_shift sur une liste non vide renvoie toujours un élément.
            $status = array_shift($this->statusQueue);
        }

        $confidence = match ($status) {
            FaceVerificationStatus::Verified => 0.98,
            default => 0.0,
        };

        return new FaceVerificationResult(
            status: $status,
            confidence: $confidence,
            modelVersion: $this->modelVersion,
            correlationId: $request->correlationId,
            reasonCode: $status === FaceVerificationStatus::Verified ? null : 'FAKE_'.$status->value,
        );
    }
}
