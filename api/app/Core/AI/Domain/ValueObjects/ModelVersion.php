<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

/**
 * Version d'un modèle d'inférence (AI-001, #6770).
 *
 * Chaque résultat d'inférence porte la version du modèle qui l'a produit :
 * les versions de modèles sont ainsi auditables (un changement de fournisseur
 * ou de version est traçable par corrélation).
 */
final readonly class ModelVersion
{
    public function __construct(
        /** Nom stable du modèle, ex. `face-verifier`, `meter-ocr`. */
        public string $model,
        /** Version du fournisseur, ex. `v2`, `2026-09`, `provider-x/1.4`. */
        public string $version,
    ) {}

    public function __toString(): string
    {
        return "{$this->model}:{$this->version}";
    }
}
