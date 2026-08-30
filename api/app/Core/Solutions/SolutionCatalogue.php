<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;

/**
 * Catalogue des solutions sectorielles — allowlist serveur.
 *
 * Les manifests sont enregistrés par les providers des modules (inversion
 * de dépendance) : ce catalogue ne référence jamais `App\Modules\*`
 * directement (garde d'isolation #5584). Un code inconnu est REFUSÉ
 * (fail-closed) — jamais de résolution dynamique par nom de classe.
 */
final class SolutionCatalogue
{
    /** @var array<string, callable(): SolutionManifest> */
    private array $factories = [];

    /**
     * @param  callable(): SolutionManifest  $factory
     */
    public function register(string $code, callable $factory): void
    {
        $this->factories[$code] = $factory;
    }

    public function has(string $code): bool
    {
        return isset($this->factories[$code]);
    }

    /** @return list<string> */
    public function codes(): array
    {
        $codes = array_keys($this->factories);
        sort($codes);

        return $codes;
    }

    public function resolve(string $code): SolutionManifest
    {
        if (! isset($this->factories[$code])) {
            throw new SolutionNotFoundException($code);
        }

        return ($this->factories[$code])();
    }
}
