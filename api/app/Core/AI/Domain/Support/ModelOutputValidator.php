<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Support;

use App\Core\AI\Domain\Enums\ModelType;
use App\Core\AI\Domain\Exceptions\ModelOutputValidationException;

/**
 * Validation par schéma des sorties de modèles (AI-001, #6770).
 *
 * Chaque ModelType déclare les clés REQUISES de son payload ({@see
 * ModelType::requiredPayloadKeys()}) et leurs types attendus. L'adaptateur
 * valide la sortie avant de construire un ModelResult exploitable par le
 * domaine : une sortie non conforme est refusée (exception), jamais acceptée
 * silencieusement.
 */
final class ModelOutputValidator
{
    /**
     * Valide un payload brut de sortie pour un type de modèle.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validate(ModelType $type, array $payload): void
    {
        foreach ($type->requiredPayloadKeys() as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new ModelOutputValidationException(
                    "Model output for {$type->value} is missing required key '{$key}'."
                );
            }
        }

        if ($type === ModelType::FaceVerification && ! is_bool($payload['verified'])) {
            throw new ModelOutputValidationException("Model output for {$type->value}: 'verified' must be a boolean.");
        }

        if ($type === ModelType::Liveness && ! is_bool($payload['live'])) {
            throw new ModelOutputValidationException("Model output for {$type->value}: 'live' must be a boolean.");
        }

        if ($type === ModelType::OcrReading) {
            if (! is_string($payload['value']) && ! is_int($payload['value']) && ! is_float($payload['value'])) {
                throw new ModelOutputValidationException("Model output for {$type->value}: 'value' must be numeric or string.");
            }

            if (! is_string($payload['unit'])) {
                throw new ModelOutputValidationException("Model output for {$type->value}: 'unit' must be a string.");
            }
        }

        if (array_key_exists('confidence', $payload)) {
            $confidence = $payload['confidence'];
            if (! is_int($confidence) && ! is_float($confidence)) {
                throw new ModelOutputValidationException("Model output for {$type->value}: 'confidence' must be numeric.");
            }

            if ($confidence < 0.0 || $confidence > 1.0) {
                throw new ModelOutputValidationException("Model output for {$type->value}: 'confidence' must be within [0, 1].");
            }
        }
    }
}
