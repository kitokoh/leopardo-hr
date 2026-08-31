<?php

declare(strict_types=1);

namespace App\Core\Privacy\Infrastructure\Services;

use App\Core\Privacy\Domain\Enums\PiiSensitivity;

/**
 * MAT-011 (#5869) — Registre de classification PII (config/pii.php).
 *
 * Fournit la politique de chaque champ sensible (contexte, sensibilité,
 * chiffrement, rétention, droits export/anonymisation/suppression) et
 * valide l'intégrité du catalogue : « chaque champ sensible possède une
 * politique » (critère d'acceptation MAT-011).
 */
final class PiiRegistry
{
    /**
     * @param  array<string, mixed>  $config  (config('pii'))
     */
    public function __construct(private readonly array $config)
    {
    }

    public function version(): string
    {
        return (string) ($this->config['version'] ?? '0.0.0');
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return array_keys($this->config['categories'] ?? []);
    }

    /**
     * Politique d'un champ sensible, fusionnée avec la politique par défaut.
     *
     * @return array{context: string, sensitivity: string, encrypted: bool, retention_months: int|null, exportable: bool, anonymizable: bool, deletable: bool}|null
     */
    public function policy(string $key): ?array
    {
        $default = $this->config['default_policy'] ?? [];
        $categories = $this->config['categories'] ?? [];

        foreach ($categories as $category) {
            $entries = is_array($category) ? ($category['entries'] ?? []) : [];

            if (is_array($entries) && array_key_exists($key, $entries)) {
                $entry = $entries[$key];

                return is_array($entry)
                    ? array_merge(is_array($default) ? $default : [], $entry)
                    : null;
            }
        }

        return null;
    }

    public function sensitivity(string $key): ?PiiSensitivity
    {
        $policy = $this->policy($key);

        if ($policy === null) {
            return null;
        }

        return PiiSensitivity::tryFrom((string) $policy['sensitivity']);
    }

    /**
     * Vérifie l'intégrité du catalogue : chaque entrée a une politique
     * complète et valide. Retourne la liste des erreurs (vide = catalogue OK).
     *
     * @return list<string>
     */
    public function validateCatalog(): array
    {
        $errors = [];
        $categories = $this->config['categories'] ?? [];

        if (! is_array($categories) || $categories === []) {
            return ['catalogue PII vide : config/pii.php doit déclarer au moins une catégorie'];
        }

        $required = ['context', 'sensitivity', 'encrypted', 'retention_months', 'exportable', 'anonymizable', 'deletable'];

        foreach ($categories as $categoryName => $category) {
            $entries = is_array($category) ? ($category['entries'] ?? []) : [];

            if (! is_array($entries) || $entries === []) {
                $errors[] = "catégorie '{$categoryName}' sans entrées";
            }

            foreach ($entries as $key => $entry) {
                if (! is_array($entry)) {
                    $errors[] = "entrée '{$key}' invalide";

                    continue;
                }

                foreach ($required as $field) {
                    if (! array_key_exists($field, $entry)) {
                        $errors[] = "entrée '{$key}' : politique incomplète (champ '{$field}' manquant)";
                    }
                }

                if (isset($entry['sensitivity']) && PiiSensitivity::tryFrom((string) $entry['sensitivity']) === null) {
                    $errors[] = "entrée '{$key}' : sensibilité inconnue '{$entry['sensitivity']}'";
                }
            }
        }

        return $errors;
    }

    /**
     * Catégories brutes du catalogue (config('pii.categories')).
     *
     * @return array<string, array{label?: string, entries?: array<string, mixed>}>
     */
    public function entries(): array
    {
        $categories = $this->config['categories'] ?? [];

        return is_array($categories) ? $categories : [];
    }

    /**
     * Champs sensibles d'un contexte (module/BC).
     *
     * @return list<string>
     */
    public function fieldsForContext(string $context): array
    {
        $fields = [];

        foreach ($this->entries() as $category) {
            $entries = $category['entries'] ?? [];

            foreach ($entries as $key => $entry) {
                if (is_array($entry) && ($entry['context'] ?? null) === $context) {
                    $fields[] = $key;
                }
            }
        }

        return $fields;
    }
}
