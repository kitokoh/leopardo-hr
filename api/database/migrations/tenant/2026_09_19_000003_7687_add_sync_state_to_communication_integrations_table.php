<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7687 (R2 Communication) — etat de synchronisation Gmail persiste
 * sur `communication_integrations` (R1 #7686) :
 *
 * - `sync_history_id` : dernier `historyId` Gmail traite — la cle de la
 *   sync INCREMENTALE (`users.history.list?startHistoryId=`). Null tant
 *   qu'aucune full sync n'a abouti ; remis a null quand Google repond 404
 *   (historyId expire) pour forcer le fallback full sync.
 * - `sync_page_token` : `nextPageToken` d'une full sync en cours — permet
 *   de reprendre une full sync interrompue (job retente, quota…) sans
 *   re-parcourir les pages deja ingerees.
 * - `last_synced_at` : derniere passe reussie (observabilite + sonde UI).
 *
 * Aucun contenu de mail ici : uniquement des curseurs opaques Gmail
 * (minimisation). Migration reentrante (garde colonne par colonne, la table
 * peut avoir ete creee par un deploy partiel), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('communication_integrations')) {
            return;
        }

        Schema::table('communication_integrations', function (Blueprint $table): void {
            if (! schemaHasColumn('communication_integrations', 'sync_history_id')) {
                $table->string('sync_history_id', 32)->nullable();
            }

            if (! schemaHasColumn('communication_integrations', 'sync_page_token')) {
                $table->string('sync_page_token', 255)->nullable();
            }

            if (! schemaHasColumn('communication_integrations', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('communication_integrations')) {
            return;
        }

        Schema::table('communication_integrations', function (Blueprint $table): void {
            foreach (['sync_history_id', 'sync_page_token', 'last_synced_at'] as $column) {
                if (schemaHasColumn('communication_integrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
