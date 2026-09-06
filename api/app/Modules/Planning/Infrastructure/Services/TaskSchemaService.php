<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Introspection du schéma de la table `tasks` pour les gardes de compatibilité
 * « montée de schéma progressive » des Actions de la couche Application.
 *
 * Les facades Laravel sont réservées à Interfaces/Infrastructure (issue #6568) :
 * les Actions délèguent ici leurs questions de schéma au lieu d'importer
 * `Illuminate\Support\Facades\Schema`.
 */
final class TaskSchemaService
{
    /**
     * Colonnes ajoutées post-MVP sur `tasks` : ignorées à l'écriture tant que
     * la table ne les porte pas (environnements pas encore migrés).
     *
     * @var list<string>
     */
    private const POST_MVP_COLUMNS = ['category', 'checklist', 'visibility'];

    /**
     * Retire de $data les colonnes post-MVP absentes de la table `tasks`
     * (compatibilité montée de schéma progressive, garde historique).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function filterToExistingColumns(array $data): array
    {
        foreach (self::POST_MVP_COLUMNS as $column) {
            if (array_key_exists($column, $data) && ! Schema::hasColumn('tasks', $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
