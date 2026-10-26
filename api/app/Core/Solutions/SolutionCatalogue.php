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
 *
 * BOS-C-02 (#8631, ADR-04) — **un seul code canonique par verticale** :
 * un code peut déclarer des ALIAS historiques à l'enregistrement. Les alias
 * restent résolus en LECTURE (`resolve()`), mais le catalogue public
 * (`canonicalCodes()`) n'expose qu'un code par offre — deux codes pour une
 * même verticale produisaient des statistiques fausses et des activations
 * partielles selon le chemin emprunté (vitrine vs survey).
 */
final class SolutionCatalogue
{
    /** @var array<string, callable(): SolutionManifest> */
    private array $factories = [];

    /** @var array<string, string> alias => code canonique */
    private array $aliases = [];

    /**
     * @param  callable(): SolutionManifest  $factory
     * @param  list<string>  $aliases  codes historiques résolus vers ce code canonique
     */
    public function register(string $code, callable $factory, array $aliases = []): void
    {
        $this->factories[$code] = $factory;

        foreach ($aliases as $alias) {
            $this->aliases[$alias] = $code;
        }
    }

    public function has(string $code): bool
    {
        return isset($this->factories[$code]) || isset($this->aliases[$code]);
    }

    /** @return list<string> */
    public function codes(): array
    {
        $codes = array_keys($this->factories);
        sort($codes);

        return $codes;
    }

    /**
     * Codes canoniques — un seul code par verticale (ADR-04).
     *
     * Les codes déclarés comme alias d'un autre code en sont exclus : le
     * catalogue public n'expose jamais deux codes pour une même offre.
     *
     * @return list<string>
     */
    public function canonicalCodes(): array
    {
        $codes = array_values(array_diff(array_keys($this->factories), array_keys($this->aliases)));
        sort($codes);

        return $codes;
    }

    /**
     * Code canonique d'un code ou d'un alias (fail-closed si inconnu).
     *
     * Sert à l'agrégation statistique : `restaurant` et `restaurantmanager`
     * ne comptent plus comme deux offres distinctes.
     */
    public function canonicalCode(string $code): string
    {
        if (isset($this->aliases[$code])) {
            return $this->aliases[$code];
        }

        if (isset($this->factories[$code])) {
            return $code;
        }

        throw new SolutionNotFoundException($code);
    }

    /** @return array<string, string> alias => code canonique */
    public function aliases(): array
    {
        $aliases = $this->aliases;
        ksort($aliases);

        return $aliases;
    }

    public function resolve(string $code): SolutionManifest
    {
        if (isset($this->factories[$code])) {
            return ($this->factories[$code])();
        }

        // Alias historique : résolu en lecture vers le code canonique.
        if (isset($this->aliases[$code])) {
            return $this->resolve($this->aliases[$code]);
        }

        throw new SolutionNotFoundException($code);
    }
}
