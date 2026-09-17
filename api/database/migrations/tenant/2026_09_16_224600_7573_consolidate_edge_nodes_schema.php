<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7573 — Consolidation du schéma Edge (`edge_nodes`), tranche de #7452.
 *
 * `edge_nodes` était déclarée **deux fois dans le schéma tenant** :
 * `2026_06_29_000001_create_edge_sync_tables.php` (première en ordre
 * d'exécution, donc **gagnante**) et `2026_06_30_000001_create_edge_nodes_table.php`
 * (no-op silencieux). Le schéma réel ne portait donc que la génération 06_29, et
 * les 9 colonnes de la génération 06_30 manquaient — alors que le code du module
 * `EdgeSync` les utilise (`RegisterEdgeNode`, `MonitorEdgeNodesCommand`,
 * `DetectSilentEdgeNodesCommand`, `PushEdgeRecords` lisent `node_id`,
 * `license_valid`, `alert_muted`, `pending_count`, …).
 *
 * Correctif **forward-only** : les 9 colonnes sont ajoutées par `Schema::table`,
 * gardées par `schemaHasColumn()`, et la déclaration concurrente est supprimée
 * dans le même commit (une table = une déclaration). Aucun `NOT NULL` n'est
 * introduit : les colonnes sont reprises telles que la génération 06_30 les
 * définit (nullable ou avec valeur par défaut), donc une base déjà peuplée passe.
 *
 * Idempotente : rejouable sur une base migrée comme sur une base fraîche.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('edge_nodes', 'node_id', static fn (Blueprint $t) => $t->string('node_id', 64)->nullable());
        $this->addColumn('edge_nodes', 'ip_address', static fn (Blueprint $t) => $t->string('ip_address', 45)->nullable());
        $this->addColumn('edge_nodes', 'version', static fn (Blueprint $t) => $t->string('version', 32)->nullable());
        $this->addColumn('edge_nodes', 'pending_count', static fn (Blueprint $t) => $t->unsignedInteger('pending_count')->default(0));
        $this->addColumn('edge_nodes', 'sync_requested_at', static fn (Blueprint $t) => $t->timestamp('sync_requested_at')->nullable());
        $this->addColumn('edge_nodes', 'license_valid', static fn (Blueprint $t) => $t->boolean('license_valid')->default(false));
        $this->addColumn('edge_nodes', 'alert_muted', static fn (Blueprint $t) => $t->boolean('alert_muted')->default(false));
        $this->addColumn('edge_nodes', 'last_alert_sent_at', static fn (Blueprint $t) => $t->timestamp('last_alert_sent_at')->nullable());
        $this->addColumn('edge_nodes', 'revoked_at', static fn (Blueprint $t) => $t->timestamp('revoked_at')->nullable());

        // `node_id` est l'identifiant métier du noeud (unique) côté code ; la
        // clé primaire réelle reste l'uuid `id` de la génération gagnante.
        $this->addUnique('edge_nodes', ['node_id'], 'edge_nodes_node_id_unique');
    }

    public function down(): void
    {
        if (! schemaTableExists('edge_nodes')) {
            return;
        }

        $present = array_values(array_filter(
            ['node_id', 'ip_address', 'version', 'pending_count', 'sync_requested_at', 'license_valid', 'alert_muted', 'last_alert_sent_at', 'revoked_at'],
            static fn (string $column): bool => schemaHasColumn('edge_nodes', $column),
        ));

        if ($present !== []) {
            Schema::table('edge_nodes', static function (Blueprint $blueprint) use ($present): void {
                $blueprint->dropColumn($present);
            });
        }
    }

    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (! schemaTableExists($table) || schemaHasColumn($table, $column)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addUnique(string $table, array $columns, string $name): void
    {
        if (! schemaTableExists($table) || $this->indexExists($name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    private function indexExists(string $name): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$name]) !== null;
    }
};
