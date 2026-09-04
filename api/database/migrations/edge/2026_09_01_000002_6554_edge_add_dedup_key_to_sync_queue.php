<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6554 — miroir SQLite de la migration tenant
 * `2026_09_01_000001_6554_add_dedup_key_to_sync_queue.php` (même colonne,
 * même index unique). L'Edge appliance exécute ses propres migrations
 * (`--path=database/migrations/edge --database=sqlite`) et ne voit JAMAIS
 * les migrations tenant.
 *
 * ⚠ Toute évolution du schéma doit être répercutée ici ET dans la migration
 * tenant (gardes : tests edge/tests + EdgeSyncDaemon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->string('dedup_key', 64)->nullable();
            $table->unique(['edge_node_id', 'dedup_key'], 'sync_queue_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->dropUnique('sync_queue_dedup_unique');
            $table->dropColumn('dedup_key');
        });
    }
};
