<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8747 [BOS-CHAT] — P2 : ordre total, anti-doublon, réponse, édition/suppression.
 *
 * Le module existant (PA2-COMM-002) ordonnait les messages par `created_at`
 * puis `id`. Deux conséquences réelles en production :
 *
 *   1. `created_at` est un `timestampTz` : deux messages écrits dans la même
 *      transaction (cas d'un import, ou d'une écriture groupée) partagent la
 *      même valeur, donc l'ordre d'affichage dépend d'un tri secondaire ;
 *   2. une pagination par `id` n'est pas un curseur stable : elle mélange
 *      l'ordre de création global (tous fils confondus) et l'ordre du fil.
 *
 * `seq` est un entier MONOTONE PAR FIL, attribué côté serveur. Il sert de
 * curseur de pagination (`before_seq` / `after_seq`) et de point de reprise
 * après reconnexion (#8749).
 *
 * Cette migration est ADDITIVE :
 *   - `seq` est backfillé pour les messages existants par
 *     `row_number() over (partition by conversation_thread_id order by created_at, id)`,
 *     donc les fils historiques obtiennent un ordre identique à celui qu'ils
 *     avaient déjà ;
 *   - `last_message_seq` est aligné sur le `max(seq)` de chaque fil ;
 *   - aucune colonne existante n'est renommée ni supprimée.
 *
 * Conventions : `docs/architecture/MIGRATIONS_CONVENTIONS.md` — `strict_types`,
 * `return new class`, réentrance par `schemaTableExists()` / `schemaHasColumn()`,
 * `down()` limité à ce que `up()` a créé, index tenant-first.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (schemaTableExists('conversation_messages')) {
            if (! schemaHasColumn('conversation_messages', 'seq')) {
                Schema::table('conversation_messages', function (Blueprint $table): void {
                    // Curseur monotone DANS le fil (pas un identifiant global).
                    $table->unsignedBigInteger('seq')->nullable();

                    // Anti-doublon : clé fournie par le client, unique par fil.
                    // L'index unique est partiel en pratique : PostgreSQL traite
                    // chaque NULL comme distinct, donc les messages sans clé
                    // client (API historique) ne se gênent pas entre eux.
                    $table->string('client_message_id', 64)->nullable();

                    // Réponse à un message du MÊME fil (vérifié côté serveur).
                    $table->unsignedBigInteger('reply_to_message_id')->nullable();

                    // Édition et suppression : colonnes explicites, et non un
                    // `updated_at` global — le modèle reste sans `UPDATED_AT`.
                    $table->timestampTz('edited_at')->nullable();
                    $table->timestampTz('deleted_at')->nullable();
                    $table->unsignedInteger('deleted_by_id')->nullable();
                });
            }

            if (! schemaHasColumn('conversation_messages', 'attachment_disk')) {
                Schema::table('conversation_messages', function (Blueprint $table): void {
                    // #8748 — disque réellement utilisé à l'écriture. Stocker le
                    // nom du disque rend la migration d'un stockage éphémère vers
                    // un stockage persistant rétro-compatible : les anciennes
                    // lignes restent lisibles sur `local`, les nouvelles vont sur
                    // le disque configuré.
                    $table->string('attachment_disk', 32)->nullable();
                });
            }

            // ── Backfill `seq` (idempotent : ne touche que les NULL) ─────────
            DB::statement(<<<'SQL'
                WITH ranked AS (
                    SELECT id,
                           row_number() OVER (
                               PARTITION BY conversation_thread_id
                               ORDER BY created_at, id
                           ) AS rn
                    FROM conversation_messages
                    WHERE seq IS NULL
                )
                UPDATE conversation_messages m
                   SET seq = ranked.rn
                  FROM ranked
                 WHERE m.id = ranked.id
            SQL);

            // ── Index ────────────────────────────────────────────────────────
            // Curseur de lecture : (fil, seq) est le seul accès chaud.
            DB::statement(<<<'SQL'
                CREATE INDEX IF NOT EXISTS conversation_messages_thread_seq_index
                    ON conversation_messages (conversation_thread_id, seq)
            SQL);

            // Anti-doublon : unique par fil, uniquement quand une clé client est
            // fournie (index partiel — les NULL restent libres).
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS conversation_messages_client_id_unique
                    ON conversation_messages (conversation_thread_id, client_message_id)
                    WHERE client_message_id IS NOT NULL
            SQL);

            // Suppression : filtrer les messages vivants sans parcourir le fil.
            DB::statement(<<<'SQL'
                CREATE INDEX IF NOT EXISTS conversation_messages_thread_deleted_index
                    ON conversation_messages (conversation_thread_id, deleted_at)
            SQL);

            // #8748 — le disque de stockage est une propriété du message.
            DB::statement(<<<'SQL'
                CREATE INDEX IF NOT EXISTS conversation_messages_reply_to_index
                    ON conversation_messages (reply_to_message_id)
                    WHERE reply_to_message_id IS NOT NULL
            SQL);

            if (! schemaHasColumn('conversation_messages', 'attachment_disk')) {
                // (défensif : la colonne vient d'être ajoutée ci-dessus)
                DB::statement('SELECT 1');
            }

            DB::statement("COMMENT ON COLUMN conversation_messages.seq IS '[BOS-CHAT #8747] Curseur monotone par fil — ordre total et pagination before_seq/after_seq.'");
            DB::statement("COMMENT ON COLUMN conversation_messages.client_message_id IS '[BOS-CHAT #8747] Clé d''idempotence fournie par le client ; unique par fil (index partiel).'");
            DB::statement("COMMENT ON COLUMN conversation_messages.reply_to_message_id IS '[BOS-CHAT #8747] Message du même fil auquel celui-ci répond.'");
            DB::statement("COMMENT ON COLUMN conversation_messages.deleted_at IS '[BOS-CHAT #8747] Suppression logique : la ligne reste pour l''ordre et les réponses.'");
            DB::statement("COMMENT ON COLUMN conversation_messages.deleted_by_id IS '[BOS-CHAT #8747] Auteur de la suppression (auteur du message, ou admin du groupe).'");
            DB::statement("COMMENT ON COLUMN conversation_messages.attachment_disk IS '[BOS-CHAT #8748] Disque de stockage réel de la pièce jointe (local, s3, ...).'");
        }

        if (schemaTableExists('conversation_threads') && ! schemaHasColumn('conversation_threads', 'last_message_seq')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                // Compteur du fil : l'attribution d'un `seq` est un
                // `UPDATE ... SET last_message_seq = last_message_seq + 1
                //  RETURNING last_message_seq`, donc un verrou de ligne
                // atomique — pas de `max(seq) + 1` sujet à une course.
                $table->unsignedBigInteger('last_message_seq')->default(0);
            });

            // Alignement sur l'état réel des messages.
            DB::statement(<<<'SQL'
                UPDATE conversation_threads t
                   SET last_message_seq = COALESCE(
                       (SELECT MAX(m.seq) FROM conversation_messages m
                         WHERE m.conversation_thread_id = t.id),
                       0
                   )
            SQL);

            DB::statement("COMMENT ON COLUMN conversation_threads.last_message_seq IS '[BOS-CHAT #8747] Dernier seq alloué dans le fil (compteur atomique).'");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('conversation_threads') && schemaHasColumn('conversation_threads', 'last_message_seq')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->dropColumn('last_message_seq');
            });
        }

        if (! schemaTableExists('conversation_messages')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS conversation_messages_reply_to_index');
        DB::statement('DROP INDEX IF EXISTS conversation_messages_thread_deleted_index');
        DB::statement('DROP INDEX IF EXISTS conversation_messages_client_id_unique');
        DB::statement('DROP INDEX IF EXISTS conversation_messages_thread_seq_index');

        $columns = [];

        foreach (['seq', 'client_message_id', 'reply_to_message_id', 'edited_at', 'deleted_at', 'deleted_by_id', 'attachment_disk'] as $column) {
            if (schemaHasColumn('conversation_messages', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('conversation_messages', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
