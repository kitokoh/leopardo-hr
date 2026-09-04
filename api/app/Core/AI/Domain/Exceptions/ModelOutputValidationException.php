<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Exceptions;

use RuntimeException;

/**
 * Payload de sortie d'un modèle non conforme au schéma de son type
 * (AI-001, #6770).
 *
 * Les sorties sont validées par schéma AVANT d'entrer dans le domaine : une
 * sortie inattendue (clé manquante, mauvais type) est une erreur de
 * fournisseur/d'adaptateur, jamais une donnée métier.
 */
final class ModelOutputValidationException extends RuntimeException {}
