<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Domain\ValueObjects\ModelResult;
use App\Core\AI\Domain\ValueObjects\ModelVersion;

/**
 * Adaptateur d'inférence fail-closed par défaut (AI-001, #6770).
 *
 * Aucun fournisseur de modèles IA configuré → `unavailable`. Les agrégats
 * métier (Attendance, FuelStation) ne reçoivent donc jamais de résultat
 * exploitable tant qu'un fournisseur réel n'est pas branché (config
 * `ai.models.inference.adapter`).
 */
final class UnavailableModelInferenceAdapter implements ModelInferencePort
{
    public function infer(ModelRequest $request): ModelResult
    {
        return new ModelResult(
            status: ModelExecutionStatus::Unavailable,
            type: $request->type,
            correlationId: $request->correlationId,
            modelVersion: new ModelVersion('model-inference', 'unconfigured'),
            reasonCode: 'MODEL_PROVIDER_NOT_CONFIGURED',
        );
    }
}
