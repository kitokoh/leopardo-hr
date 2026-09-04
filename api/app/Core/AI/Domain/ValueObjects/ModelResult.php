<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Domain\Enums\ModelType;

/**
 * Résultat d'inférence neutre (AI-001, #6770).
 *
 * - `confidence` est normalisée sur [0, 1] par l'adaptateur ;
 * - `reason_code` est un code machine stable (jamais de libellé libre) ;
 * - `payload` est validé par schéma (ModelOutputValidator) avant d'entrer
 *   dans le domaine ;
 * - la version du modèle est toujours portée (auditabilité).
 */
final readonly class ModelResult
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public ModelExecutionStatus $status,
        public ModelType $type,
        public string $correlationId,
        public ModelVersion $modelVersion,
        public float $confidence = 0.0,
        public ?string $reasonCode = null,
        public ?array $payload = null,
        public ?float $latencyMs = null,
    ) {}

    /** L'inférence est exploitable par le domaine (succès + payload validé). */
    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }
}
