<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

use App\Core\AI\Domain\Enums\FaceVerificationStatus;

/**
 * Résultat de vérification faciale neutre (BIO-001, #6762).
 *
 * Le domaine (Attendance) traduit ce résultat vers ses propres valeurs
 * (VerificationResult) ; il ne connaît jamais la réponse brute du fournisseur.
 */
final readonly class FaceVerificationResult
{
    public function __construct(
        public FaceVerificationStatus $status,
        public float $confidence,
        public ModelVersion $modelVersion,
        public string $correlationId,
        public ?string $reasonCode = null,
        public ?float $latencyMs = null,
    ) {}

    public function isVerified(): bool
    {
        return $this->status->isVerified();
    }
}
