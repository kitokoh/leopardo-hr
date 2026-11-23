<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use Illuminate\Support\Collection;

/**
 * #9838 (BC-14 INTEGRATION) — accès au catalogue, filtré par audience.
 *
 * Point unique de lecture du catalogue : les deux surfaces (plateforme et
 * tenant) passent par ici, ce qui garantit qu'une entrée `audience = platform`
 * (les banques de l'éditeur) ne peut pas apparaître dans le catalogue d'un
 * client, même si un contrôleur l'oubliait.
 *
 * Le catalogue est mis en cache par requête (propriété d'instance) : un écran
 * qui liste les connexions puis résout leurs schémas ne fait pas N requêtes.
 */
class ConnectorCatalog
{
    /** @var Collection<string, ConnectorCatalogEntry>|null */
    private ?Collection $cache = null;

    /**
     * Entrées proposées à une audience, triées pour l'affichage.
     *
     * @param  'platform'|'tenant'  $audience
     * @return list<ConnectorCatalogEntry>
     */
    public function forAudience(string $audience): array
    {
        return $this->all()
            ->filter(static fn (ConnectorCatalogEntry $entry): bool => $entry->isAvailableFor($audience))
            ->sortBy([['sort_order', 'asc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Entrée par clé, quelle que soit son audience.
     *
     * Utilisée pour RÉSOUDRE une connexion existante : une entrée désactivée
     * ou retirée du catalogue doit rester lisible (sinon les connexions
     * historiques deviendraient orphelines et invisibles).
     */
    public function find(string $key): ?ConnectorCatalogEntry
    {
        return $this->all()->get($key);
    }

    /**
     * Entrée par clé, mais SEULEMENT si elle est proposable à cette audience.
     *
     * C'est la garde d'écriture : on ne crée pas une connexion à un connecteur
     * qui n'est pas destiné à cette surface.
     *
     * @param  'platform'|'tenant'  $audience
     */
    public function findAvailable(string $key, string $audience): ?ConnectorCatalogEntry
    {
        $entry = $this->find($key);

        if ($entry === null || ! $entry->isAvailableFor($audience)) {
            return null;
        }

        return $entry;
    }

    /**
     * Toutes les entrées, indexées par clé.
     *
     * @return Collection<string, ConnectorCatalogEntry>
     */
    public function all(): Collection
    {
        if ($this->cache === null) {
            $this->cache = ConnectorCatalogEntry::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->keyBy('key');
        }

        return $this->cache;
    }

    /**
     * Vide le cache d'instance (utile en test après un seed).
     */
    public function flush(): void
    {
        $this->cache = null;
    }
}
