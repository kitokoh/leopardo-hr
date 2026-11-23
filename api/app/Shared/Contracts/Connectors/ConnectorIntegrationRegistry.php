<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Connectors;

/**
 * #9846 (BC-14 INTEGRATION) — registre des intégrations extérieures.
 *
 * Chaque module propriétaire d'une intégration y déclare sa source depuis son
 * propre `ServiceProvider` (`ConnectorIntegrationRegistry::register()`). Le
 * module Connecteurs ne fait que lire le registre : il n'importe aucun module
 * métier, ce qui satisfait la garde d'isolation (#5584).
 *
 * L'ordre d'enregistrement est celui des providers, donc non déterministe :
 * le consommateur trie par `key()` avant d'afficher.
 */
class ConnectorIntegrationRegistry
{
    /** @var array<string, ConnectorIntegrationSourceInterface> */
    private array $sources = [];

    public function register(ConnectorIntegrationSourceInterface $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * Sources visibles sur une surface donnée, triées par clé.
     *
     * @param  'platform'|'tenant'  $audience
     * @return list<ConnectorIntegrationSourceInterface>
     */
    public function forAudience(string $audience): array
    {
        $matching = array_filter(
            $this->sources,
            static fn (ConnectorIntegrationSourceInterface $source): bool => in_array($audience, $source->audiences(), true),
        );

        ksort($matching);

        return array_values($matching);
    }

    /** @return list<ConnectorIntegrationSourceInterface> */
    public function all(): array
    {
        $sources = $this->sources;
        ksort($sources);

        return array_values($sources);
    }
}
