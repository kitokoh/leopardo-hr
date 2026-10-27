<?php

declare(strict_types=1);

namespace App\Shared\Establishments;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Establishments\EstablishmentProvider;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910) \u2014 agr\u00e9gateur cross-verticale.
 *
 * Collecte les providers tagu\u00e9s `establishments.provider`, ne garde que ceux
 * dont la verticale est activ\u00e9e pour la soci\u00e9t\u00e9, et fusionne leurs items.
 *
 * Aucune table, aucune \u00e9criture : composition de lecture pure. Ce service
 * n'importe AUCUNE classe `App\Modules\*` \u2014 c'est ce qui permet d'ajouter une
 * verticale sans toucher au socle (garde d'isolation #5584).
 */
final class EstablishmentAggregationService
{
    /**
     * @param  iterable<EstablishmentProvider>  $providers
     */
    public function __construct(private readonly iterable $providers) {}

    /**
     * Verticales R\u00c9ELLEMENT activ\u00e9es pour cette soci\u00e9t\u00e9, tri\u00e9es.
     *
     * Sert de source de v\u00e9rit\u00e9 c\u00f4t\u00e9 frontend pour afficher (ou masquer)
     * l'entr\u00e9e de navigation \u00ab \u00c9tablissements \u00bb : visible d\u00e8s qu'UNE verticale
     * \u00e0 \u00e9tablissements est active.
     *
     * @return list<string>
     */
    public function verticals(Company $company): array
    {
        $verticals = [];

        foreach ($this->providers as $provider) {
            if ($provider->enabledFor($company)) {
                $verticals[$provider->vertical()] = true;
            }
        }

        $verticals = array_keys($verticals);
        sort($verticals);

        return $verticals;
    }

    /**
     * Liste unifi\u00e9e des \u00e9tablissements de toutes les verticales activ\u00e9es.
     *
     * Tri : par verticale (ordre stable des providers), puis par nom. Le tri
     * par nom est fait ici \u2014 en PHP, pas en SQL \u2014 parce que la collation d'une
     * base et celle d'une autre ne garantissent pas le m\u00eame ordre, et que le
     * hub affiche les verticales en groupes s\u00e9par\u00e9s.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function gather(Company $company): array
    {
        /** @var array<string, list<array<string, mixed>>> $byVertical */
        $byVertical = [];
        $total = 0;

        foreach ($this->providers as $provider) {
            $vertical = $provider->vertical();

            if (! $provider->enabledFor($company)) {
                continue;
            }

            $items = [];

            foreach ($provider->establishments($company) as $item) {
                $items[] = $item->toArray();
            }

            usort($items, static function (array $a, array $b): int {
                // La forme est garantie par `EstablishmentItem::toArray()` :
                // `name` est toujours une chaîne, pas besoin de re-v\u00e9rifier.
                return strcasecmp($a['name'], $b['name']);
            });

            $byVertical[$vertical] = $items;
            $total += count($items);
        }

        $data = [];

        foreach ($byVertical as $items) {
            foreach ($items as $item) {
                $data[] = $item;
            }
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                // Verticales activ\u00e9es, m\u00eame sans \u00e9tablissement : le frontend
                // distingue \u00ab module actif, aucun \u00e9tablissement \u00bb (invite \u00e0 cr\u00e9er)
                // de \u00ab module inactif \u00bb (rien \u00e0 afficher).
                'verticals' => $this->verticals($company),
                'counts' => array_map('count', $byVertical),
            ],
        ];
    }
}
