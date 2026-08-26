<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use PHPUnit\Framework\TestCase;

/**
 * #5577 — Garde CI anti-collision de routes (niveau SOURCE).
 *
 * Deux déclarations de la même route (méthodes + URI complète) sont un bug
 * silencieux : Laravel ne garde dans sa collection que la DERNIÈRE
 * enregistrée (RouteCollection indexée par (méthodes, uri)), l'autre
 * implémentation est donc inatteignable — validations, RBAC et réponses
 * divergent sans aucun signe. Famille de bugs déjà constatée :
 * - POST /accounting/documents/{document}/payments déclaré deux fois
 *   (AccountingDocumentController::payments vs AccountingPaymentController::store) ;
 * - PUT /attendance/corrections/{correction}/reject dupliqué (rh.php) ;
 * - bloc day-closures copié-collé dans geo.php.
 *
 * ⚠️ `Route::getRoutes()` NE PEUT PAS détecter ces doublons (la collection
 * écrase la précédente déclaration) : cette garde analyse donc les FICHIERS
 * de routes, en simulant la pile des préfixes (`prefix()`), l'imbrication
 * des groupes (`group()`) et les `require` inline, pour comparer les
 * couples (méthodes normalisées, URI complète) tels que déclarés.
 *
 * Sources couvertes :
 * - api.php (avec le préfixe implicite `api` ajouté par `withRouting`) ;
 * - web.php ;
 * - les fichiers `require`-és inline (modules de routes) ;
 * - les fichiers de routes de modules chargés via `loadRoutesFrom()`
 *   (app/Modules/.../routes/ — préfixes absolus, hors groupe api.php).
 *
 * Une redéclaration intentionnelle n'existe pas : un alias déprécié a
 * toujours une méthode ou une URI différente.
 *
 * NB : classe volontairement indépendante du TestCase Laravel (pas de base
 * de données) — le scan est purement statique.
 */
class RouteCollisionGuardTest extends TestCase
{
    public function test_no_route_is_declared_twice_with_same_method_and_uri(): void
    {
        $declarations = $this->collectRouteDeclarations();
        $this->assertGreaterThan(500, count($declarations), 'le scan source doit couvrir la surface réelle');

        $byKey = [];
        foreach ($declarations as $declaration) {
            $byKey[$declaration['key']][] = $declaration['location'];
        }

        $collisions = array_filter(
            $byKey,
            static fn (array $locations): bool => count($locations) > 1,
        );

        $this->assertSame([], $collisions, 'Collision de routes détectée dans les fichiers de routes : '.
            (string) json_encode($collisions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Analyse tous les fichiers de routes et retourne leurs déclarations.
     *
     * @return list<array{key: string, location: string}>
     */
    private function collectRouteDeclarations(): array
    {
        $apiRoot = dirname(__DIR__, 3).'/routes/api.php';
        $webRoot = dirname(__DIR__, 3).'/routes/web.php';

        $moduleRoots = glob(dirname(__DIR__, 3).'/app/Modules/*/routes/*.php') ?: [];

        // api.php est chargé par withRouting() avec le préfixe implicite `api`
        // (d'où les URIs runtime /api/v1/...) ; web.php et les fichiers de
        // modules (préfixes absolus) n'ont aucun préfixe hérité.
        $roots = [
            [$apiRoot, ['api']],
            [$webRoot, []],
        ];
        foreach ($moduleRoots as $moduleRouteFile) {
            $roots[] = [$moduleRouteFile, []];
        }

        $declarations = [];
        $seenFiles = [];
        foreach ($roots as [$root, $inheritedPrefixes]) {
            $declarations = [...$declarations, ...$this->parseRouteFile($root, $inheritedPrefixes, $seenFiles)];
        }

        return $declarations;
    }

    /**
     * Parse un fichier de routes : pile de préfixes + imbrication des groupes
     * + `require` inline (résolus avec la pile courante).
     *
     * @param  list<string>  $inheritedPrefixes
     * @param  array<string, true>  $seenFiles
     * @return list<array{key: string, location: string}>
     */
    private function parseRouteFile(string $file, array $inheritedPrefixes, array &$seenFiles): array
    {
        $real = realpath($file);

        if ($real === false || isset($seenFiles[$real])) {
            return [];
        }
        $seenFiles[$real] = true;

        $src = (string) file_get_contents($real);
        $src = preg_replace('#//[^\n]*#', '', $src) ?? $src;
        $src = preg_replace('#/\*.*?\*/#s', '', $src) ?? $src;

        $declarations = [];
        // Préfixes : paires [valeur, profondeur]. Les préfixes hérités
        // (withRouting `api`, groupe `v1` parent) sont de profondeur 0 et ne
        // sont jamais retirés.
        $prefixes = array_map(static fn (string $p): array => [$p, 0], $inheritedPrefixes);
        $pending = []; // préfixes déclarés en attente d'affectation (fluent `->prefix('x')->group(...)`)
        $depth = 0;

        foreach (preg_split('/\R/', $src) ?: [] as $index => $line) {
            $delta = substr_count($line, '{') - substr_count($line, '}');

            // Le préfixe peut être déclaré sur la ligne AVANT le `->group(`
            // (fluent chain multi-lignes) : on l'affecte au groupe qui s'ouvre.
            if ($delta > 0) {
                foreach ($pending as $value) {
                    $prefixes[] = [$value, $depth + 1];
                }
                $pending = [];
                $depth += $delta;
            }

            foreach ($this->extractPrefixes($line) as $prefix) {
                $pending[] = $prefix;
            }

            // require inline : les routes du fichier requis héritent de la
            // pile de préfixes courante (ex. modules/rh.php dans le groupe v1).
            foreach ($this->extractRequires($line) as $required) {
                // `require __DIR__.'/modules/rh.php'` capture `/modules/rh.php` :
                // on retire le `/` initial avant de joindre au dossier courant.
                $requiredPath = dirname($real).DIRECTORY_SEPARATOR.ltrim($required, '/\\');
                $declarations = [...$declarations, ...$this->parseRouteFile($requiredPath, array_column($prefixes, 0), $seenFiles)];
            }

            foreach ($this->extractRouteCalls($line) as $call) {
                // Préfixe déclaré sans groupe (rare) : s'applique au niveau courant.
                if ($pending !== []) {
                    foreach ($pending as $value) {
                        $prefixes[] = [$value, $depth];
                    }
                    $pending = [];
                }

                if (str_starts_with($call['uri'], 'http')) {
                    continue;
                }
                // Jointure des segments de préfixe avec '/' (parité Laravel :
                // prefix('api') + prefix('v1') → 'api/v1').
                $prefixValues = array_column($prefixes, 0);
                $fullUri = ($prefixValues === [] ? '' : rtrim(implode('/', $prefixValues), '/').'/')
                    .ltrim($call['uri'], '/');
                $key = implode('|', $call['methods']).' '.$fullUri;

                $declarations[] = [
                    'key' => $key,
                    'location' => sprintf('%s:%d → %s (%s)', basename($real), $index + 1, $fullUri, implode(',', $call['methods'])),
                ];
            }

            if ($delta < 0) {
                // Fermeture du groupe : retrait des préfixes affectés à cette
                // profondeur, puis décrément.
                $prefixes = array_values(array_filter(
                    $prefixes,
                    static fn (array $entry): bool => $entry[1] !== $depth,
                ));
                $pending = [];
                $depth += $delta;
            }
        }

        return $declarations;
    }

    /**
     * @return list<string> chemins requis par `require __DIR__.'/...'`
     */
    private function extractRequires(string $line): array
    {
        preg_match_all(
            "/require\s+(?:__DIR__\s*\.\s*)?['\"]([^'\"]+)['\"]/",
            $line,
            $matches,
        );

        return $matches[1] ?? [];
    }

    /**
     * @return list<string>
     */
    private function extractPrefixes(string $line): array
    {
        preg_match_all(
            "/prefix\s*\(\s*['\"]([^'\"]+)['\"]/",
            $line,
            $matches,
        );

        return $matches[1] ?? [];
    }

    /**
     * @return list<array{methods: list<string>, uri: string}>
     */
    private function extractRouteCalls(string $line): array
    {
        $calls = [];
        preg_match_all('/Route::(get|post|put|patch|delete|any|match)\s*\(([^;]*)\)/', $line, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $verb = strtolower((string) $match[1]);
            $args = (string) $match[2];

            if ($verb === 'match') {
                preg_match('/\[([^\]]*)\]/', $args, $methodMatch);
                if (! isset($methodMatch[1])) {
                    continue;
                }
                preg_match_all("/['\"]([^'\"]+)['\"]/", $methodMatch[1], $names);
                $methods = array_map('strtoupper', $names[1] ?? []);
            } else {
                $methods = [$verb === 'any' ? 'ANY' : strtoupper($verb)];
            }

            preg_match("/['\"]([^'\"]+)['\"]/", $args, $uriMatch);
            if (! isset($uriMatch[1])) {
                continue;
            }

            $normalizedMethods = array_values(array_unique($methods));
            sort($normalizedMethods);

            $calls[] = ['methods' => $normalizedMethods, 'uri' => (string) $uriMatch[1]];
        }

        return $calls;
    }
}
