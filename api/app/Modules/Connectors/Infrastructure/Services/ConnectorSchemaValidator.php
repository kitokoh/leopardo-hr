<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Modules\Connectors\Domain\Exceptions\ConnectorSchemaViolationException;
use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;

/**
 * #9838 (BC-14 INTEGRATION) — validation d'une saisie CONTRE le schéma du
 * catalogue.
 *
 * C'est la pièce qui rend l'onglet « Connecteurs » réellement dynamique : le
 * backend ne connaît aucun type de connecteur. Il lit `field_schema` et
 * applique les mêmes règles pour tous — allowlist des champs, champs requis,
 * type, options d'un `select`.
 *
 * Conséquences voulues :
 *   - un champ absent du schéma est IGNORÉ (jamais stocké) : on ne peut pas
 *     écrire une clé arbitraire dans `config` ou `secrets` ;
 *   - un champ `required` manquant est un 422 explicite, pas un `null` en base ;
 *   - un `select` hors options est refusé (pas de valeur libre dans un champ
 *     fermé).
 *
 * Le service ne touche pas à la base : il est pur et testable seul.
 */
class ConnectorSchemaValidator
{
    /**
     * Valide et normalise une saisie.
     *
     * @param  array<string, mixed>  $config   champs non secrets saisis
     * @param  array<string, mixed>  $secrets  champs secrets saisis
     * @return array{config: array<string, string>, secrets: array<string, string>}
     *
     * @throws ConnectorSchemaViolationException
     */
    public function validate(ConnectorCatalogEntry $entry, array $config, array $secrets): array
    {
        $violations = [];
        $normalizedConfig = [];
        $normalizedSecrets = [];

        foreach ($entry->fields() as $field) {
            $key = (string) $field['key'];
            $isSecret = ($field['secret'] ?? false) === true;
            $required = ($field['required'] ?? false) === true;
            $type = (string) ($field['type'] ?? 'text');

            $raw = $isSecret ? ($secrets[$key] ?? null) : ($config[$key] ?? null);

            // Une valeur absente ou vide n'est pas une erreur en soi : seul un
            // champ REQUIS vide l'est. Un secret vide au PATCH signifie
            // « conserver la valeur en place » (write-only), donc il n'est
            // simplement pas renvoyé dans la sortie.
            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                if ($required) {
                    $violations[] = sprintf('Le champ « %s » est obligatoire.', $this->label($field));
                }

                continue;
            }

            if (! is_scalar($raw)) {
                $violations[] = sprintf('Le champ « %s » doit être une valeur simple.', $this->label($field));

                continue;
            }

            $value = trim((string) $raw);

            $error = $this->checkType($type, $value, $field);
            if ($error !== null) {
                $violations[] = sprintf('Le champ « %s » %s', $this->label($field), $error);

                continue;
            }

            if ($isSecret) {
                $normalizedSecrets[$key] = $value;
            } else {
                $normalizedConfig[$key] = $value;
            }
        }

        if ($violations !== []) {
            throw new ConnectorSchemaViolationException($violations);
        }

        return ['config' => $normalizedConfig, 'secrets' => $normalizedSecrets];
    }

    /**
     * Contrôle de type. Rend `null` si la valeur est acceptable, sinon la fin
     * de phrase expliquant pourquoi elle ne l'est pas.
     *
     * @param  array<string, mixed>  $field
     */
    private function checkType(string $type, string $value, array $field): ?string
    {
        return match ($type) {
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? 'doit être une URL valide (https://…).'
                : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'doit être une adresse e-mail valide.'
                : null,
            'number' => is_numeric($value) ? null : 'doit être un nombre.',
            'tel' => preg_match('/^\+?[0-9 ().-]{6,25}$/', $value) === 1
                ? null
                : 'doit être un numéro de téléphone valide.',
            'select' => $this->checkSelect($value, $field),
            'text', 'password', 'textarea' => null,
            default => null,
        };
    }

    /**
     * Un `select` est un champ FERMÉ : la valeur doit figurer dans `options`.
     *
     * @param  array<string, mixed>  $field
     */
    private function checkSelect(string $value, array $field): ?string
    {
        $options = $field['options'] ?? [];

        if (! is_array($options) || $options === []) {
            // Un select sans options est une erreur de catalogue, pas de
            // saisie : on n'invalide pas la valeur de l'utilisateur pour ça.
            return null;
        }

        $allowed = [];
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value'])) {
                $allowed[] = (string) $option['value'];
            } elseif (is_scalar($option)) {
                $allowed[] = (string) $option;
            }
        }

        return in_array($value, $allowed, true)
            ? null
            : sprintf('doit être l\'une des valeurs proposées (%s).', implode(', ', $allowed));
    }

    /**
     * Libellé lisible d'un champ, pour les messages d'erreur.
     *
     * @param  array<string, mixed>  $field
     */
    private function label(array $field): string
    {
        $label = $field['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : (string) $field['key'];
    }
}
