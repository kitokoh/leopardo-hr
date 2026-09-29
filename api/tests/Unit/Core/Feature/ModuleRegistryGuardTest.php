<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Feature;

use App\Core\Feature\Domain\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * BOS-011 (#8198, ADR-0026 FR-6) — garde d'enregistrement CI :
 * « module non enregistré = échec ».
 *
 * Toute clé de feature référencée par le code DOIT exister dans le registre
 * unifié :
 *   1. littéraux `$company->hasFeature('<clé>')` (gates `module.*` et autres) ;
 *   2. littéraux `FeatureFlag::enabled('<clé>')` ;
 *   3. constantes `*Features::CLÉ` passées à `hasFeature()` (résolues à leur
 *      valeur déclarée) ;
 *   4. codes des manifests de solution (`code()`) — y compris `self::CODE` ;
 *   5. gates `module.*` déclarés dans bootstrap/app.php, résolus via le
 *      middleware aliéné jusqu'à la clé qu'il évalue.
 *
 * Cette garde généralise et remplace les tests de registre par verticale
 * (TravelAgencyModuleRegistryTest, RestaurantManagerModuleRegistryTest, …)
 * ajoutés après chaque incident — elle échoue AVANT la prochaine désync.
 *
 * Test pur (aucune base) : lecture statique des fichiers sources.
 */
class ModuleRegistryGuardTest extends TestCase
{
    private ModuleRegistry $registry;

    private string $apiRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleRegistry;
        $this->apiRoot = dirname(__DIR__, 4);
    }

    public function test_every_referenced_feature_key_is_registered(): void
    {
        $referenced = [];

        foreach ($this->phpFiles($this->apiRoot.'/app') as $file) {
            $source = (string) file_get_contents($file);

            // Receveurs à EXCLURE : l'ancien registre d'inventaire API
            // (`FeatureRegistryInterface` / `FeatureRegistry`, sujet de
            // BOS-015) expose aussi une méthode hasFeature() — ses clés ne
            // sont PAS des flags tenant (ex. demo_employee_management).
            $legacyRegistryReceivers = $this->legacyRegistryReceivers($source);

            // 1 + 2 : littéraux directs.
            foreach (['hasFeature', 'enabled'] as $method) {
                $pattern = $method === 'hasFeature'
                    ? '/(?<receiver>\$[A-Za-z_][A-Za-z0-9_]*)->hasFeature\(\s*\'([a-z0-9_]+)\'\s*\)/'
                    : '/FeatureFlag::enabled\(\s*\'([a-z0-9_]+)\'/';

                if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
                    foreach ($matches as $match) {
                        if ($method === 'hasFeature' && in_array($match['receiver'], $legacyRegistryReceivers, true)) {
                            continue;
                        }

                        $referenced[$match[2] ?? $match[1]][] = $this->relative($file);
                    }
                }
            }

            // 3 : constantes *Features passées à hasFeature().
            if (preg_match_all('/->hasFeature\(\s*([A-Za-z]+)::([A-Z0-9_]+)\s*\)/', $source, $matches, PREG_SET_ORDER) !== false) {
                foreach ($matches as $match) {
                    $value = $this->resolveConstantValue($match[1], $match[2]);

                    if ($value !== null) {
                        $referenced[$value][] = $this->relative($file)." ({$match[1]}::{$match[2]})";
                    }
                }
            }
        }

        // 4 : codes des manifests de solution.
        foreach ($this->solutionManifestFiles() as $file) {
            $code = $this->manifestCode($file);

            if ($code !== null) {
                $referenced[$code][] = $this->relative($file).' (manifest code())';
            }
        }

        // 5 : gates module.* de bootstrap/app.php → clé évaluée par le middleware.
        foreach ($this->moduleGateKeys() as $alias => $key) {
            if ($key !== null) {
                $referenced[$key][] = "bootstrap/app.php (gate {$alias})";
            }
        }

        $unregistered = [];

        foreach ($referenced as $key => $sites) {
            if (! $this->registry->isKnown($key)) {
                $unregistered[] = "« {$key} » référencé par : ".implode(' ; ', array_unique($sites));
            }
        }

        sort($unregistered);

        self::assertSame(
            [],
            $unregistered,
            "FR-6 — clés de feature référencées absentes du ModuleRegistry :\n - ".implode("\n - ", $unregistered)
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)),
            '/\.php$/'
        );

        $files = [];

        foreach ($iterator as $file) {
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return str_replace($this->apiRoot.'/', '', $file);
    }

    /**
     * Variables d'un fichier typées `FeatureRegistryInterface` /
     * `FeatureRegistry` (ancien registre d'inventaire API) — leurs appels
     * hasFeature() ne visent pas les flags tenant.
     *
     * @return list<string>
     */
    private function legacyRegistryReceivers(string $source): array
    {
        if (! str_contains($source, 'FeatureRegistryInterface') && ! str_contains($source, 'FeatureRegistry')) {
            return [];
        }

        $receivers = [];

        if (preg_match_all('/(?:FeatureRegistryInterface|FeatureRegistry)\s+(\$[A-Za-z_][A-Za-z0-9_]*)/', $source, $matches) !== false) {
            foreach ($matches[1] as $variable) {
                $receivers[] = $variable;
            }
        }

        return array_values(array_unique($receivers));
    }

    /**
     * Résout la valeur d'une constante `Classe::CONST` (classes *Features du
     * repo, ex. ShowcaseFeatures::COMPANY_SHOWCASE ⇒ 'company_showcase').
     */
    private function resolveConstantValue(string $class, string $constant): ?string
    {
        foreach ($this->phpFiles($this->apiRoot.'/app') as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/\bclass '.$class.'\b/', $source) !== 1) {
                continue;
            }

            if (preg_match('/const '.$constant.'\s*=\s*\'([a-z0-9_]+)\'/', $source, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function solutionManifestFiles(): array
    {
        $files = [];

        foreach (glob($this->apiRoot.'/app/Modules/*/Domain/Solution/*Manifest.php') ?: [] as $file) {
            $files[] = $file;
        }

        foreach (glob($this->apiRoot.'/app/Modules/*/Domain/Manifests/*Manifest.php') ?: [] as $file) {
            $files[] = $file;
        }

        sort($files);

        return $files;
    }

    /**
     * Code déclaré par un manifest (`return '<code>'` ou `return self::CODE`
     * dans la méthode code()).
     */
    private function manifestCode(string $file): ?string
    {
        $source = (string) file_get_contents($file);

        if (preg_match('/function code\(\):\s*string\s*\{(?<body>.*?)\n    \}/s', $source, $method) !== 1) {
            return null;
        }

        if (preg_match('/return\s*\'([a-z0-9_]+)\'\s*;/', $method['body'], $match) === 1) {
            return $match[1];
        }

        if (str_contains($method['body'], 'return self::CODE;')
            && preg_match('/const CODE\s*=\s*\'([a-z0-9_]+)\'/', $source, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * Gates `module.*` déclarés dans bootstrap/app.php, résolus jusqu'à la
     * clé de feature évaluée par leur middleware.
     *
     * @return array<string, ?string> alias (ex. module.showcase) ⇒ clé résolue
     */
    private function moduleGateKeys(): array
    {
        $bootstrap = (string) file_get_contents($this->apiRoot.'/bootstrap/app.php');
        $gates = [];

        if (preg_match_all('/\'(module\.[a-z0-9_]+)\'\s*=>\s*([A-Za-z\\\\]+)::class/', $bootstrap, $matches, PREG_SET_ORDER) === false) {
            return $gates;
        }

        foreach ($matches as $match) {
            [$full, $alias, $middlewareClass] = $match;
            $shortName = basename(str_replace('\\', '/', $middlewareClass));
            $file = $this->apiRoot.'/app/Http/Middleware';
            $middlewareFile = null;

            foreach ($this->phpFiles($file) as $candidate) {
                if (basename($candidate) === $shortName.'.php') {
                    $middlewareFile = $candidate;
                    break;
                }
            }

            if ($middlewareFile === null) {
                $gates[$alias] = null;

                continue;
            }

            $source = (string) file_get_contents($middlewareFile);

            if (preg_match('/->hasFeature\(\s*\'([a-z0-9_]+)\'\s*\)/', $source, $keyMatch) === 1) {
                $gates[$alias] = $keyMatch[1];

                continue;
            }

            if (preg_match('/->hasFeature\(\s*([A-Za-z]+)::([A-Z0-9_]+)\s*\)/', $source, $keyMatch) === 1) {
                $gates[$alias] = $this->resolveConstantValue($keyMatch[1], $keyMatch[2]);

                continue;
            }

            $gates[$alias] = null;
        }

        return $gates;
    }
}
