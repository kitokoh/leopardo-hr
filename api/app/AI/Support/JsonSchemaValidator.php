<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * BOS-034 (#8223) — validateur JSON Schema minimaliste, sans dépendance.
 *
 * Les `inputSchema`/`outputSchema` des AIToolDefinition et les `parameters`
 * du registre (`ai_tool_registry`) utilisent un sous-ensemble stable de JSON
 * Schema (inventorié sur les 6 catalogues de tools) : `type`, `properties`,
 * `required`, `enum`, `minimum`/`maximum`, `minLength`/`maxLength`, `items`,
 * `additionalProperties` (booléen). Ce validateur couvre exactement ce
 * sous-ensemble ; les clés de schéma inconnues (`description`, métadonnées)
 * sont ignorées — jamais de refus sur une construction non supportée
 * (fail-open sur le schéma, fail-closed sur les données : une violation
 * CONNUE est la seule voie de rejet).
 *
 * Chaque violation est rendue sous la forme `chemin: règle attendue` (ex.
 * `absence_id: type integer attendu`).
 */
final class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public function validate(mixed $data, array $schema, string $path = ''): array
    {
        $violations = [];

        $type = $schema['type'] ?? null;
        if (is_string($type) && ! $this->matchesType($data, $type)) {
            $violations[] = $this->at($path).": type {$type} attendu, ".$this->typeOf($data).' reçu';

            return $violations;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($data, $schema['enum'], true)) {
            $violations[] = $this->at($path).': valeur hors enum ['.implode(', ', array_map(
                static fn (mixed $v): string => is_scalar($v) ? (string) $v : gettype($v),
                $schema['enum'],
            )).']';
        }

        if (is_array($data) && $this->isAssoc($data)) {
            $violations = array_merge($violations, $this->validateObject($data, $schema, $path));
        }

        if (is_array($data) && ! $this->isAssoc($data) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($data as $index => $item) {
                $violations = array_merge($violations, $this->validate($item, $schema['items'], $this->at($path).'['.$index.']'));
            }
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && is_numeric($schema['minimum']) && $data < $schema['minimum']) {
                $violations[] = $this->at($path).": minimum {$schema['minimum']} attendu";
            }
            if (isset($schema['maximum']) && is_numeric($schema['maximum']) && $data > $schema['maximum']) {
                $violations[] = $this->at($path).": maximum {$schema['maximum']} attendu";
            }
        }

        if (is_string($data)) {
            if (isset($schema['minLength']) && is_numeric($schema['minLength']) && mb_strlen($data) < (int) $schema['minLength']) {
                $violations[] = $this->at($path).": minLength {$schema['minLength']} attendu";
            }
            if (isset($schema['maxLength']) && is_numeric($schema['maxLength']) && mb_strlen($data) > (int) $schema['maxLength']) {
                $violations[] = $this->at($path).": maxLength {$schema['maxLength']} attendu";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function validateObject(array $data, array $schema, string $path): array
    {
        $violations = [];

        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $key) {
                if (is_string($key) && ! array_key_exists($key, $data)) {
                    $violations[] = $this->at($path).": clé requise « {$key} » absente";
                }
            }
        }

        $properties = $schema['properties'] ?? [];
        if (is_array($properties)) {
            foreach ($properties as $key => $propertySchema) {
                if (! is_string($key) || ! is_array($propertySchema) || ! array_key_exists($key, $data)) {
                    continue;
                }

                $violations = array_merge(
                    $violations,
                    $this->validate($data[$key], $propertySchema, $this->at($path).'.'.$key),
                );
            }
        }

        $additional = $schema['additionalProperties'] ?? true;
        if ($additional === false && is_array($properties)) {
            foreach (array_keys($data) as $key) {
                if (! is_string($key) || ! array_key_exists($key, $properties)) {
                    $violations[] = $this->at($path).": clé « {$key} » non déclarée (additionalProperties: false)";
                }
            }
        }

        return $violations;
    }

    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            // Le tableau PHP vide est accepté pour les deux types : décodé
            // en tableau associatif, un objet JSON `{}` et un tableau `[]`
            // sont indiscernables (json_decode(..., true)).
            'object' => is_array($data) && ($data === [] || $this->isAssoc($data)),
            'array' => is_array($data) && ($data === [] || ! $this->isAssoc($data)),
            'string' => is_string($data),
            // Le LLM émet parfois des entiers en chaîne (« 12 ») : rester
            // strict sur le type JSON réel — c'est précisément ce que la
            // validation runtime doit bloquer (BOS-034).
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => true, // type inconnu du sous-ensemble : pas de refus (fail-open schéma).
        };
    }

    private function typeOf(mixed $data): string
    {
        return match (true) {
            is_array($data) => $this->isAssoc($data) ? 'object' : 'array',
            is_string($data) => 'string',
            is_int($data) => 'integer',
            is_float($data) => 'number',
            is_bool($data) => 'boolean',
            $data === null => 'null',
            default => gettype($data),
        };
    }

    /**
     * Un tableau PHP est un « object » JSON s'il est associatif ; le tableau
     * vide est accepté pour les deux types (JSON ne le distingue pas).
     *
     * @param  array<mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }

    private function at(string $path): string
    {
        return $path === '' ? '$' : $path;
    }
}
