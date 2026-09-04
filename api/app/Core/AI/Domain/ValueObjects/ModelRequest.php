<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

use App\Core\AI\Domain\Enums\ModelType;

/**
 * Requête d'inférence neutre (AI-001, #6770).
 *
 * L'entrée (`input`) est un tableau de données neutres (références de fichiers
 * internes, métadonnées) : aucun format propriétaire de fournisseur ne circule
 * dans le domaine. Le `correlation_id` permet de rattacher chaque appel à un
 * événement métier (tenant, appareil, tentative).
 *
 * @param  array<string, mixed>  $input
 */
final readonly class ModelRequest
{
    /**
     * @param  array<string, mixed>  $input  entrée neutre (références de fichiers internes, métadonnées)
     */
    public function __construct(
        public ModelType $type,
        public string $correlationId,
        public array $input,
        public int $timeoutMs = 15_000,
    ) {}
}
