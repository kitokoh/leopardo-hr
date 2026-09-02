<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Contracts;

use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Domain\ValueObjects\ModelResult;

/**
 * Contrat commun d'inférence des modèles IA (AI-001, #6770).
 *
 * Face verification, liveness et OCR passent par des contrats cohérents :
 * requête/réponse neutres, version de modèle auditée, sorties validées par
 * schéma, timeout borné. Un fournisseur peut être remplacé sans modifier les
 * agrégats métier — seule la résolution de l'implémentation (configuration)
 * change.
 *
 * Invariant d'appel (responsabilité de l'APPELANT, pas du port) : aucune
 * inférence ne peut être exécutée sans contexte tenant et appareil
 * authentifiés (BIO-001, #6762). Les implémentations ne doivent jamais
 * journaliser le contenu des entrées (photos, gabarits).
 */
interface ModelInferencePort
{
    public function infer(ModelRequest $request): ModelResult;
}
