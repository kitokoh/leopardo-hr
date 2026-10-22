<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8629 (Lot 5 — diagnostic de synchronisation) : rendre un échec de rejeu
 * OBSERVABLE.
 *
 * Avant cette migration, `SyncEngineService::push()` journalisait l'erreur
 * dans les logs applicatifs puis remettait l'item en `pending` ou `failed`
 * sans rien persister de la cause. Un exploitant ne pouvait donc pas
 * distinguer « file vide » de « 40 items en échec depuis trois jours » :
 * le diagnostic imposait de fouiller les logs, voire de ne rien voir du tout.
 *
 * Colonnes ajoutées (toutes nullables → aucune écriture existante cassée) :
 *  - `idempotency_key`   clé de rejeu FOURNIE par le nœud Edge, indépendante de
 *                        l'empreinte du payload : la même opération métier
 *                        rejouée avec un horodatage différent reste un doublon.
 *  - `last_error`        dernière erreur applicative (tronquée) pour diagnostic.
 *  - `last_attempt_at`   horodatage de la dernière tentative (âge de la file).
 *  - `conflict_at`       horodatage du passage en conflit.
 *
 * ⚠ Miroir obligatoire dans `database/migrations/edge` (schéma SQLite de
 * l'Edge) — garde automatisée : `tests/Feature/EdgeSync/EdgeSchemaParityTest.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_queue', function (Blueprint $table): void {
            // La clé d'idempotence est OPAQUE et fournie par l'appelant : elle
            // n'est jamais dérivée du payload, sinon un simple changement
            // d'horodatage produirait une nouvelle empreinte et donc un doublon.
            $table->string('idempotency_key', 128)->nullable()->after('dedup_key');
            $table->text('last_error')->nullable()->after('conflict_note');
            $table->timestamp('last_attempt_at')->nullable()->after('last_error');
            $table->timestamp('conflict_at')->nullable()->after('last_attempt_at');
        });

        // Index NON unique : deux nœuds différents peuvent légitimement émettre
        // la même clé (l'unicité réelle est portée par
        // `sync_queue_dedup_unique` sur (edge_node_id, dedup_key)).
        Schema::table('sync_queue', function (Blueprint $table): void {
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
