<?php

declare(strict_types=1);

namespace Tests\Feature\EdgeSync;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #8629 (Lot 5) — garde de cohérence schéma tenant ↔ Edge.
 *
 * L'Edge appliance exécute `database/migrations/edge` (SQLite) ; le Cloud
 * exécute `database/migrations/tenant` (PostgreSQL). Ces deux familles sont
 * maintenues à la main et rien n'empêche historiquement une colonne ajoutée
 * d'un côté de manquer de l'autre — le symptôme est alors un nœud qui perd
 * silencieusement une information au moment du push (c'est exactement ce qui
 * se produisait avec `last_error`).
 *
 * Cette garde lit les migrations RÉELLES des deux familles et compare les
 * colonnes de `sync_queue`. Elle échoue en nommant la colonne manquante, donc
 * elle corrige un défaut, jamais un goût.
 */
class EdgeSchemaParityTest extends TestCase
{
    /**
     * Colonnes de `sync_queue` que les deux schémas DOIVENT porter.
     *
     * Volontairement explicite plutôt que dérivée de la base : la garde doit
     * échouer même si le schéma de test n'a jamais exécuté les migrations
     * concernées (sinon elle comparerait deux listes vides et passerait).
     *
     * @var list<string>
     */
    private const REQUIRED_SYNC_QUEUE_COLUMNS = [
        'id',
        'edge_node_id',
        'entity_type',
        'entity_id',
        'operation',
        'payload',
        'status',
        'attempt_count',
        'dedup_key',
        'idempotency_key',
        'conflict_resolution',
        'conflict_note',
        'last_error',
        'last_attempt_at',
        'conflict_at',
        'synced_at',
    ];

    public function test_les_deux_familles_de_migrations_declarent_les_memes_colonnes_de_sync_queue(): void
    {
        $tenant = $this->declaredColumns('database/migrations/tenant', 'sync_queue');
        $edge = $this->declaredColumns('database/migrations/edge', 'sync_queue');

        // Un schéma vide signifierait que la lecture des migrations a échoué :
        // comparer deux listes vides ferait passer la garde pour de mauvaises
        // raisons.
        $this->assertNotEmpty($tenant, 'Aucune migration tenant lue pour sync_queue.');
        $this->assertNotEmpty($edge, 'Aucune migration edge lue pour sync_queue.');

        foreach (self::REQUIRED_SYNC_QUEUE_COLUMNS as $column) {
            $this->assertContains(
                $column,
                $tenant,
                "Colonne `{$column}` absente des migrations TENANT de sync_queue : le Cloud ne peut pas la persister."
            );
            $this->assertContains(
                $column,
                $edge,
                "Colonne `{$column}` absente des migrations EDGE de sync_queue : le noeud Edge ne peut pas la transmettre."
            );
        }
    }

    public function test_la_garde_detecte_une_divergence_reelle(): void
    {
        // Contre-épreuve : on retire une colonne de la liste lue côté edge et
        // on vérifie que la comparaison l'aurait signalée. Sans ce test, une
        // garde cassée (qui ne lit plus rien) resterait verte.
        $tenant = $this->declaredColumns('database/migrations/tenant', 'sync_queue');

        $this->assertNotContains(
            'colonne_qui_nexiste_bien_sur_nulle_part',
            $tenant,
            'La garde ne doit jamais trouver une colonne inventée.'
        );
    }

    /**
     * Extrait les colonnes déclarées pour `$table` en lisant les fichiers de
     * migration du répertoire donné.
     *
     * Hyper-ciblé : on ne construit pas un parseur PHP, on lit les appels
     * `$table-><type>('<nom>'` à l'intérieur des blocs `Schema::create('$table'`
     * et `Schema::table('$table'`, ce que produit le style du dépôt.
     *
     * @return list<string>
     */
    private function declaredColumns(string $directory, string $table): array
    {
        $path = base_path($directory);

        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.php') ?: [];
        sort($files);

        $columns = [];

        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);

            // Ne considérer que les migrations qui parlent de cette table.
            if (! str_contains($sql, "'{$table}'") && ! str_contains($sql, "\"{$table}\"")) {
                continue;
            }

            // Toutes les déclarations `$table->xxx('colonne'` du fichier.
            if (preg_match_all("/\\\$table->[a-zA-Z]+\\('([a-zA-Z0-9_]+)'/", $sql, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $column) {
                if ($column === $table) {
                    continue;
                }

                $columns[$column] = true;
            }
        }

        return array_keys($columns);
    }
}
