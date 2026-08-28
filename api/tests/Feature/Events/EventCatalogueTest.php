<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * MAT-006 (#5864) — Catalogue d'événements versionnés.
 *
 * Vérifie que `docs/architecture/event-catalogue.yaml` est cohérent avec le
 * code :
 *  - parité : chaque classe d'événement réelle a une entrée catalogue, et
 *    chaque entrée référence une classe existante ;
 *  - versionnement : versions semver uniques par événement ;
 *  - chaque événement possède un schema testable (JSON Schema draft-07,
 *    sous-ensemble) et un sample qui valide ce schema ;
 *  - dépréciation : toute entrée dépréciée porte `removal_at`.
 *
 * Référentiel : docs/architecture/EVENT_CATALOGUE.md
 */
class EventCatalogueTest extends TestCase
{
    private const CATALOGUE = '../docs/architecture/event-catalogue.yaml';

    private const SEMVER = '/^[0-9]+\.[0-9]+\.[0-9]+$/';

    /**
     * @return array{version: string, owner: string, events: list<array<string, mixed>>}
     */
    private function catalogue(): array
    {
        $path = base_path(self::CATALOGUE);
        self::assertFileExists($path, 'le catalogue machine doit exister');

        /** @var array{version: string, owner: string, events: list<array<string, mixed>>} $catalogue */
        $catalogue = Yaml::parseFile($path);

        return $catalogue;
    }

    public function test_catalogue_is_readable_and_has_events(): void
    {
        $catalogue = $this->catalogue();

        self::assertNotEmpty($catalogue['version'], 'le catalogue porte une version');
        self::assertNotEmpty($catalogue['owner'], 'le catalogue porte un owner');
        self::assertGreaterThan(0, count($catalogue['events']), 'au moins un événement catalogue');
    }

    public function test_every_event_class_has_a_catalogue_entry(): void
    {
        $names = $this->declaredEventNames();

        $realClasses = $this->realEventClasses();

        foreach ($realClasses as $class) {
            self::assertTrue(
                $this->catalogueContainsClass($names, $class),
                "la classe d'événement {$class} doit avoir une entrée dans le catalogue"
            );
        }
    }

    public function test_every_entry_references_an_existing_class(): void
    {
        $realClasses = $this->realEventClasses();

        foreach ($this->catalogue()['events'] as $event) {
            $class = (string) $event['class'];
            $short = substr($class, (int) strrpos($class, '\\') + 1);

            self::assertTrue(
                in_array($class, $realClasses, true) || in_array($short, $realClasses, true),
                "l'entrée catalogue {$event['name']} référence une classe introuvable : {$class}"
            );
        }
    }

    public function test_versions_are_unique_and_semver(): void
    {
        $seen = [];

        foreach ($this->catalogue()['events'] as $event) {
            $name = (string) $event['name'];
            $version = (string) $event['version'];

            self::assertMatchesRegularExpression(
                self::SEMVER,
                $version,
                "la version de {$name} doit être semver (X.Y.Z)"
            );

            $key = $name.'@'.$version;
            self::assertArrayNotHasKey(
                $key,
                $seen,
                "doublon de version pour {$name} : {$version}"
            );
            $seen[$key] = true;
        }
    }

    public function test_each_schema_is_a_valid_json_schema(): void
    {
        foreach ($this->catalogue()['events'] as $event) {
            $schema = $event['schema'];

            self::assertIsArray($schema, "schema de {$event['name']} manquant");
            self::assertSame('object', $schema['type'] ?? null, "schema de {$event['name']} doit être un objet");
            self::assertArrayHasKey('required', $schema, "schema de {$event['name']} doit déclarer required");
            self::assertArrayHasKey('properties', $schema, "schema de {$event['name']} doit déclarer properties");
            self::assertIsList($schema['required'], "required de {$event['name']} doit être une liste");

            foreach ($schema['required'] as $required) {
                self::assertArrayHasKey(
                    $required,
                    $schema['properties'],
                    "le champ requis '{$required}' de {$event['name']} doit exister dans properties"
                );
            }
        }
    }

    public function test_each_sample_validates_against_its_schema(): void
    {
        foreach ($this->catalogue()['events'] as $event) {
            $sample = $event['sample'] ?? null;
            $schema = $event['schema'];

            self::assertNotNull($sample, "sample de {$event['name']} manquant");

            $errors = $this->validateAgainstSchema($sample, $schema);

            self::assertSame(
                [],
                $errors,
                "le sample de {$event['name']} ne valide pas son schema :\n - ".implode("\n - ", $errors)
            );
        }
    }

    public function test_deprecated_entries_carry_removal_at(): void
    {
        foreach ($this->catalogue()['events'] as $event) {
            $deprecation = $event['deprecation'] ?? null;

            if ($deprecation !== null) {
                self::assertIsArray($deprecation, "deprecation de {$event['name']} doit être un objet");
                self::assertArrayHasKey(
                    'removal_at',
                    $deprecation,
                    "l'événement déprécié {$event['name']} doit porter removal_at"
                );
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function realEventClasses(): array
    {
        $classes = [];

        foreach (File::glob(app_path('Events/*.php')) ?: [] as $file) {
            $classes[] = basename($file, '.php');
        }

        foreach (File::glob(app_path('Modules/*/Domain/Events/*.php')) ?: [] as $file) {
            $classes[] = basename($file, '.php');
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function declaredEventNames(): array
    {
        return array_map(
            static fn (array $event): string => (string) $event['name'],
            $this->catalogue()['events']
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function catalogueContainsClass(array $names, string $class): bool
    {
        foreach ($this->catalogue()['events'] as $event) {
            $declared = (string) $event['class'];
            $short = substr($declared, (int) strrpos($declared, '\\') + 1);

            if ($declared === $class || $short === $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valide une valeur contre un sous-ensemble de JSON Schema draft-07.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function validateAgainstSchema(mixed $value, array $schema, string $path = '$'): array
    {
        $errors = [];
        $type = $schema['type'] ?? null;

        if (! $this->typeMatches($value, $type)) {
            return ["{$path} : type attendu {$type}, reçu ".gettype($value)];
        }

        switch ($type) {
            case 'object':
                if (! is_array($value)) {
                    return ["{$path} : objet attendu"];
                }

                foreach ($schema['required'] ?? [] as $required) {
                    if (! array_key_exists($required, $value)) {
                        $errors[] = "{$path}.{$required} : champ requis manquant";
                    }
                }

                foreach ($schema['properties'] ?? [] as $name => $propertySchema) {
                    if (! array_key_exists($name, $value)) {
                        continue;
                    }
                    $errors = [
                        ...$errors,
                        ...$this->validateAgainstSchema($value[$name], $propertySchema, "{$path}.{$name}"),
                    ];
                }
                break;

            case 'array':
                if (! is_array($value)) {
                    return ["{$path} : tableau attendu"];
                }
                foreach ($value as $index => $item) {
                    $errors = [
                        ...$errors,
                        ...$this->validateAgainstSchema($item, $schema['items'] ?? [], "{$path}[{$index}]"),
                    ];
                }
                break;

            case 'string':
                if (! is_string($value)) {
                    return ["{$path} : chaîne attendue"];
                }
                if (isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) {
                    $errors[] = "{$path} : longueur minimale {$schema['minLength']}";
                }
                if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
                    $errors[] = "{$path} : longueur maximale {$schema['maxLength']}";
                }
                if (isset($schema['pattern']) && preg_match('#'.$schema['pattern'].'#', $value) !== 1) {
                    $errors[] = "{$path} : ne respecte pas le pattern {$schema['pattern']}";
                }
                if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
                    $errors[] = "{$path} : valeur hors enum ".implode(',', $schema['enum']);
                }
                if (isset($schema['format']) && ! $this->matchesFormat($value, $schema['format'])) {
                    $errors[] = "{$path} : format {$schema['format']} invalide ({$value})";
                }
                break;

            case 'integer':
            case 'number':
                if (! is_int($value) && ! is_float($value)) {
                    return ["{$path} : nombre attendu"];
                }
                if ($type === 'integer' && ! is_int($value)) {
                    return ["{$path} : entier attendu"];
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    $errors[] = "{$path} : minimum {$schema['minimum']}";
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    $errors[] = "{$path} : maximum {$schema['maximum']}";
                }
                break;

            case 'boolean':
                if (! is_bool($value)) {
                    return ["{$path} : booléen attendu"];
                }
                break;

            case 'null':
                if ($value !== null) {
                    return ["{$path} : null attendu"];
                }
                break;
        }

        return $errors;
    }

    private function typeMatches(mixed $value, ?string $type): bool
    {
        return match ($type) {
            'object' => is_array($value),
            'array' => is_array($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    private function matchesFormat(string $value, string $format): bool
    {
        return match ($format) {
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            'date-time' => strtotime($value) !== false,
            'date' => preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value) === 1,
            default => true,
        };
    }
}
