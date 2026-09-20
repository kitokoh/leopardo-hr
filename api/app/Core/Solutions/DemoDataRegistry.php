<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Solutions\Exceptions\DemoDataKitNotFoundException;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Registre des kits de données de démonstration — allowlist serveur (#7865).
 *
 * Les kits sont enregistrés par les providers des modules (inversion de
 * dépendance) : ce registre ne référence jamais `App\Modules\*` directement
 * (garde d'isolation #5584), même pattern que `SolutionCatalogue`. Un code
 * inconnu est REFUSÉ (fail-closed) — jamais de résolution dynamique par nom
 * de classe. Les factories sont paresseuses : aucun seeder n'est instancié
 * tant que `seed()` n'est pas appelé.
 */
final class DemoDataRegistry
{
    /** @var array<string, callable(): DemoDataKit> */
    private array $factories = [];

    /**
     * @param  callable(): DemoDataKit  $factory
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

    /**
     * Résout le kit du code donné et installe le jeu de démonstration
     * (idempotence garantie par le contrat `DemoDataKit`).
     */
    public function seed(string $code, Company $company): void
    {
        if (! isset($this->factories[$code])) {
            throw new DemoDataKitNotFoundException($code);
        }

        ($this->factories[$code])()->seed($company);
    }
}
