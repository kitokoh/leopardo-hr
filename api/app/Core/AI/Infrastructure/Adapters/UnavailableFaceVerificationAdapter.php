<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Domain\ValueObjects\FaceVerificationRequest;
use App\Core\AI\Domain\ValueObjects\FaceVerificationResult;
use App\Core\AI\Domain\ValueObjects\ModelVersion;

/**
 * Adaptateur fail-closed par défaut (BIO-001, #6762).
 *
 * Aucun fournisseur de vérification faciale configuré → `provider_unavailable`.
 * Un pointage facial n'est donc JAMAIS accepté tant qu'un fournisseur réel
 * n'a pas été branché (config `ai.models.face_verification.adapter`).
 */
final class UnavailableFaceVerificationAdapter implements FaceVerificationPort
{
    public function verify(FaceVerificationRequest $request): FaceVerificationResult
    {
        return new FaceVerificationResult(
            status: FaceVerificationStatus::ProviderUnavailable,
            confidence: 0.0,
            modelVersion: new ModelVersion('face-verifier', 'unconfigured'),
            correlationId: $request->correlationId,
            reasonCode: 'FACE_PROVIDER_NOT_CONFIGURED',
        );
    }
}
