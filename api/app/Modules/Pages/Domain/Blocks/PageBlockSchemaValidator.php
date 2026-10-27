<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Valide le contenu d'un bloc contre le schéma déclaré par son fournisseur
 * (BC PAGES, #8724).
 *
 * ## Ce que ce validateur fait de plus que celui de Showcase
 *
 * Celui de Showcase interprétait un sous-ensemble JSON Schema suffisant pour
 * sept types connus à la compilation. Ici les schémas viennent de BC tiers :
 * on doit donc défendre périmètre ET surface d'attaque.
 *
 * 1. **`additionalProperties: false` est le défaut.** L'absence de la clé vaut
 *    refus, pas « tout est permis ». Un fournisseur qui oublie de borner son
 *    schéma se fait fermer par défaut, pas ouvrir — c'est le sens du
 *    « schéma strict » de l'issue.
 * 2. **Allowlist de protocoles d'URL.** Tout champ `format: uri` est vérifié :
 *    seuls `https:`/`http:` (et `mailto:`/`tel:` pour les liens de contact)
 *    passent. `javascript:`, `data:`, `vbscript:` sont refusés — un
 *    `javascript:` dans un `cta_url` est un XSS stocké qui contourne toutes les
 *    protections de rendu.
 * 3. **Bornes de profondeur et de cardinalité.** Un schéma peut déclarer
 *    `maxItems` ; on impose en plus un plafond dur et une profondeur maximale
 *    pour qu'aucun fournisseur ne puisse faire exploser la mémoire ou le rendu
 *    avec un contenu légitimement conforme mais absurde.
 *
 * ## Ce que ce validateur NE fait PAS
 *
 * Il ne nettoie pas de HTML : il n'y a pas de HTML dans le modèle. Le contenu
 * est une structure de données, le renderer décide du markup. C'est pour ça
 * qu'aucun `strip_tags` n'apparaît ici — nettoyer donnerait une fausse
 * impression de sécurité sur un champ qui pourrait servir dans un attribut.
 */
final class PageBlockSchemaValidator
{
    /** Profondeur maximale d'imbrication d'un contenu de bloc. */
    public const MAX_DEPTH = 6;

    /** Nombre maximal d'éléments dans un tableau, plafond dur (en plus du schéma). */
    public const MAX_ARRAY_ITEMS = 100;

    /** Longueur maximale d'une chaîne, plafond dur (en plus du schéma). */
    public const MAX_STRING_LENGTH = 5000;

    /** Protocoles autorisés pour un champ `format: uri`. */
    public const ALLOWED_URI_SCHEMES = ['https', 'http'];

    /** Protocoles autorisés pour un champ `format: contact-uri` (lien direct). */
    public const ALLOWED_CONTACT_SCHEMES = ['https', 'http', 'mailto', 'tel'];

    /**
     * @param  array<string, mixed>  $content
     * @return list<string> Erreurs lisibles, vides si le contenu est valide.
     */
    public function validate(PageBlockTypeDefinition $definition, array $content): array
    {
        $errors = [];
        $this->checkNode($definition->schema, $content, 'content', $errors, 0);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function isValid(PageBlockTypeDefinition $definition, array $content): bool
    {
        return $this->validate($definition, $content) === [];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkNode(array $schema, mixed $value, string $pointer, array &$errors, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $errors[] = sprintf('%s : profondeur maximale (%d) dépassée.', $pointer, self::MAX_DEPTH);

            return;
        }

        $expected = $schema['type'] ?? 'object';

        switch ($expected) {
            case 'object':
                $this->checkObject($schema, $value, $pointer, $errors, $depth);
                break;

            case 'array':
                $this->checkArray($schema, $value, $pointer, $errors, $depth);
                break;

            case 'string':
                $this->checkString($schema, $value, $pointer, $errors);
                break;

            case 'integer':
                if (! is_int($value)) {
                    $errors[] = sprintf('%s : un entier est attendu.', $pointer);
                }
                break;

            case 'number':
                if (! is_int($value) && ! is_float($value)) {
                    $errors[] = sprintf('%s : un nombre est attendu.', $pointer);
                }
                break;

            case 'boolean':
                if (! is_bool($value)) {
                    $errors[] = sprintf('%s : un booléen est attendu.', $pointer);
                }
                break;

            default:
                // Type de schéma inconnu : fail-closed. Un fournisseur qui
                // invente `"type": "any"` ne doit pas ouvrir une brèche.
                $errors[] = sprintf('%s : type de schéma non supporté (« %s »).', $pointer, (string) $expected);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkObject(array $schema, mixed $value, string $pointer, array &$errors, int $depth): void
    {
        if (! is_array($value)) {
            $errors[] = sprintf('%s : un objet est attendu.', $pointer);

            return;
        }

        /** @var array<string, mixed> $properties */
        $properties = $schema['properties'] ?? [];

        /** @var list<string> $required */
        $required = $schema['required'] ?? [];
        foreach ($required as $key) {
            if (! array_key_exists($key, $value)) {
                $errors[] = sprintf('%s.%s : champ requis manquant.', $pointer, $key);
            }
        }

        // Défaut strict : sans `additionalProperties` explicite, une clé
        // inconnue est refusée (le contraire de JSON Schema, volontairement).
        $allowAdditional = ($schema['additionalProperties'] ?? false) === true;

        foreach ($value as $key => $child) {
            $key = (string) $key;

            if (! array_key_exists($key, $properties)) {
                if (! $allowAdditional) {
                    $errors[] = sprintf('%s.%s : champ non autorisé.', $pointer, $key);
                }

                continue;
            }

            /** @var array<string, mixed> $childSchema */
            $childSchema = $properties[$key];
            $this->checkNode($childSchema, $child, $pointer.'.'.$key, $errors, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkArray(array $schema, mixed $value, string $pointer, array &$errors, int $depth): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $errors[] = sprintf('%s : une liste est attendue.', $pointer);

            return;
        }

        $count = count($value);

        $minItems = $schema['minItems'] ?? null;
        if (is_int($minItems) && $count < $minItems) {
            $errors[] = sprintf('%s : au moins %d élément(s) attendu(s) (reçu %d).', $pointer, $minItems, $count);
        }

        $maxItems = $schema['maxItems'] ?? null;
        $effectiveMax = is_int($maxItems) ? min($maxItems, self::MAX_ARRAY_ITEMS) : self::MAX_ARRAY_ITEMS;
        if ($count > $effectiveMax) {
            $errors[] = sprintf('%s : au plus %d élément(s) autorisé(s) (reçu %d).', $pointer, $effectiveMax, $count);
        }

        /** @var array<string, mixed>|null $itemsSchema */
        $itemsSchema = $schema['items'] ?? null;
        if (! is_array($itemsSchema)) {
            return;
        }

        foreach ($value as $index => $item) {
            $this->checkNode($itemsSchema, $item, sprintf('%s.%d', $pointer, (int) $index), $errors, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkString(array $schema, mixed $value, string $pointer, array &$errors): void
    {
        if (! is_string($value)) {
            $errors[] = sprintf('%s : une chaîne est attendue.', $pointer);

            return;
        }

        $length = mb_strlen($value, 'UTF-8');

        $maxLength = $schema['maxLength'] ?? null;
        $effectiveMax = is_int($maxLength) ? min($maxLength, self::MAX_STRING_LENGTH) : self::MAX_STRING_LENGTH;
        if ($length > $effectiveMax) {
            $errors[] = sprintf('%s : longueur maximale %d dépassée (%d).', $pointer, $effectiveMax, $length);
        }

        $minLength = $schema['minLength'] ?? null;
        if (is_int($minLength) && $length < $minLength) {
            $errors[] = sprintf('%s : longueur minimale %d non atteinte (%d).', $pointer, $minLength, $length);
        }

        if (array_key_exists('enum', $schema) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = sprintf(
                '%s : valeur non autorisée (attendu : %s).',
                $pointer,
                implode(', ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : gettype($v), $schema['enum'])),
            );
        }

        $format = $schema['format'] ?? null;
        if ($format === 'uri' || $format === 'contact-uri') {
            $this->checkUri($value, $pointer, $errors, $format === 'contact-uri');
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function checkUri(string $value, string $pointer, array &$errors, bool $allowContactSchemes): void
    {
        // Les URL relatives sont autorisées (liens internes `/p/{slug}`) : elles
        // ne peuvent pas porter de protocole exécutable.
        if ($value === '' || str_starts_with($value, '/')) {
            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (! is_string($scheme) || $scheme === '') {
            $errors[] = sprintf('%s : URL invalide (protocole absent).', $pointer);

            return;
        }

        $allowed = $allowContactSchemes ? self::ALLOWED_CONTACT_SCHEMES : self::ALLOWED_URI_SCHEMES;
        if (! in_array(strtolower($scheme), $allowed, true)) {
            $errors[] = sprintf(
                '%s : protocole « %s » refusé (autorisés : %s).',
                $pointer,
                strtolower($scheme),
                implode(', ', $allowed),
            );
        }
    }
}
