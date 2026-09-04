<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

/**
 * Requête de vérification faciale neutre (BIO-001, #6762).
 *
 * Le port ne connaît ni le fournisseur, ni son format de réponse, ni ses
 * exceptions : la requête porte des RÉFÉRENCES internes (gabarit enrôlé,
 * capture temporaire) résolues par l'adaptateur via sa propre configuration.
 * Le `correlation_id` rattache l'appel à l'événement métier (tenant,
 * appareil, tentative de pointage).
 */
final readonly class FaceVerificationRequest
{
    public function __construct(
        public string $correlationId,
        /** Référence du gabarit enrôlé (BIO-003, #6764) — jamais le gabarit brut. */
        public string $templateReference,
        /** Référence de la capture temporaire à comparer. */
        public string $captureReference,
        public int $timeoutMs = 15_000,
    ) {}
}
