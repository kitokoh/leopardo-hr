<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8629 — miroir SQLite de la migration tenant
 * `2026_10_17_140000_8629_add_diagnostics_to_sync_queue.php` (mêmes colonnes,
 * même index). L'Edge appliance exécute ses propres migrations
 * (`--path=database/migrations/edge --database=sqlite`) et ne voit JAMAIS les
 * migrations tenant : sans ce miroir, un nœud Edge ne pourrait pas persister
 * la clé d'idempotence ni la cause de son dernier échec.
 *
 * ⚠ Garde automatisée : `tests/Feature/EdgeSync/EdgeSchemaParityTest.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_queue', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('conflict_at')->nullable();
            $table->index(['edge_node_id', 'idempotency_key'], 'sync_queue_idempotency_index');
        });
    }

    public function down(): void
    {
        Schema::table('sync_queue', function (Blueprint $table): void {
            $table->dropIndex('sync_queue_idempotency_index');
            $table->dropColumn(['idempotency_key', 'last_error', 'last_attempt_at', 'conflict_at']);
        });
    }
};
