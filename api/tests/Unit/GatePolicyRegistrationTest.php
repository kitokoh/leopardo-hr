<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * PA2-ARCH-008: garantit qu'un seul provider enregistre Gate::policy() pour un
 * modele donne. Avant ce test, `Invoice` etait enregistre a la fois dans
 * AppServiceProvider (-> BillingPolicy) et AuthServiceProvider (-> InvoicePolicy),
 * et le resultat dependait silencieusement de l'ordre de boot dans
 * bootstrap/providers.php. Voir docs/PLAN_ACTION2/09_AUDIT_MODULES_API_STRUCTURE.md.
 *
 * Le test parcourt tous les fichiers *ServiceProvider.php de app/ (providers
 * "globaux" + providers de module) et echoue si un meme FQCN de modele est
 * passe en premier argument de Gate::policy() dans plus d'un fichier.
 */
class GatePolicyRegistrationTest extends TestCase
{
    public function test_no_model_has_gate_policy_registered_in_more_than_one_provider(): void
    {
        $basePath = dirname(__DIR__, 2);

        $finder = new Finder();
        $finder->files()
            ->in($basePath.'/app')
            ->name('*ServiceProvider.php');

        /** @var array<string, list<string>> $registrations model FQCN => list of provider files */
        $registrations = [];

        foreach ($finder as $file) {
            $contents = (string) $file->getContents();

            if (! str_contains($contents, 'Gate::policy(')) {
                continue;
            }

            $useMap = $this->extractUseStatements($contents);

            foreach ($this->extractGatePolicyModelArgs($contents) as $modelShortNameOrFqcn) {
                $modelShortNameOrFqcn = ltrim($modelShortNameOrFqcn, '\\');
                $modelFqcn = ltrim($useMap[$modelShortNameOrFqcn] ?? $modelShortNameOrFqcn, '\\');
                $registrations[$modelFqcn][] = $file->getRelativePathname();
            }
        }

        $duplicates = array_filter(
            $registrations,
            static fn (array $providers): bool => count(array_unique($providers)) > 1
        );

        $this->assertSame(
            [],
            $duplicates,
            'Ces modeles ont un Gate::policy() enregistre dans plusieurs providers a la fois: '
            .print_r($duplicates, true)
        );
    }

    /**
     * @return array<string, string> short class name => fully qualified class name
     */
    private function extractUseStatements(string $contents): array
    {
        $map = [];

        preg_match_all('/^use\s+([^;]+);/m', $contents, $matches);

        foreach ($matches[1] as $use) {
            $use = trim($use);
            // Ignore `use Trait;` inside class bodies is not matched here since
            // we only look at `^use ` at column 0 (import statements are always
            // at the top level, unindented, in this codebase).
            $shortName = str_contains($use, '\\') ? substr($use, (int) strrpos($use, '\\') + 1) : $use;
            $map[$shortName] = $use;
        }

        return $map;
    }

    /**
     * @return list<string> first-argument class references (short name or ::class expr) for each Gate::policy(...) call
     */
    private function extractGatePolicyModelArgs(string $contents): array
    {
        preg_match_all('/Gate::policy\(\s*([A-Za-z0-9_\\\\]+)::class\s*,/', $contents, $matches);

        return $matches[1];
    }
}
