<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8746 [BOS-CHAT] — Groupes de discussion : participants, rôles et lecture.
 *
 * Le système de conversation existant (PA2-COMM-002, `conversation_threads`)
 * est STRICTEMENT binaire : `employee_id` + `manager_id`, deux participants et
 * deux seulement. L'appartenance est déduite de ces deux colonnes, donc il est
 * impossible d'exprimer un groupe, un rôle intra-conversation (créateur /
 * admin / membre), un ajout ou un retrait, ni un accusé de lecture distinct
 * par personne.
 *
 * Cette migration est ADDITIVE et RÉTRO-COMPATIBLE :
 *   - `conversation_participants` ne référence QUE les fils `kind = 'group'` ;
 *     les fils `direct` existants continuent de fonctionner avec
 *     `employee_id`/`manager_id`, sans backfill obligatoire ;
 *   - `kind` vaut `direct` par défaut pour toutes les lignes existantes ;
 *   - `created_by` reste NULL pour les fils historiques.
 *
 * Conventions : `docs/architecture/MIGRATIONS_CONVENTIONS.md` — `strict_types`,
 * `return new class`, réentrance par `schemaTableExists()` / `schemaHasColumn()`,
 * `down()` limité à ce que `up()` a créé, index tenant-first.
 *
 * Aucune FK vers `companies` (table public) : le lien tenant est la colonne
 * `company_id` UUID indexée.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('conversation_thread_id');
                $table->unsignedInteger('employee_id');
                // creator | admin | member — le rôle est évalué côté SERVEUR par
                // ConversationParticipantPolicy ; jamais fourni par le client
                // lors d'un changement de rôle.
                $table->string('role', 20)->default('member');
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestampTz('joined_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['conversation_thread_id', 'employee_id'],
                    'conversation_participants_thread_employee_unique',
                );
                $table->index(['company_id', 'employee_id'], 'conversation_participants_company_employee_index');
                $table->index(['company_id', 'conversation_thread_id'], 'conversation_participants_company_thread_index');

                $table->foreign('conversation_thread_id')
                    ->references('id')->on('conversation_threads')
                    ->cascadeOnDelete();
            });
        }

        if (schemaTableExists('conversation_threads') && ! schemaHasColumn('conversation_threads', 'kind')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->string('kind', 20)->default('direct');
                $table->unsignedInteger('created_by')->nullable();
            });

            // Index de lecture des groupes d'un utilisateur sans passer par les
            // colonnes binaires historiques.
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->index(['company_id', 'kind'], 'conversation_threads_company_kind_index');
            });

            DB::statement("COMMENT ON COLUMN conversation_threads.kind IS '[BOS-CHAT #8746] direct = fil binaire historique employe/manager ; group = fil a participants multiples (conversation_participants).'");
            DB::statement("COMMENT ON COLUMN conversation_threads.created_by IS '[BOS-CHAT #8746] Auteur du fil (renseigne pour les groupes ; NULL pour les fils historiques).'");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('conversation_threads') && schemaHasColumn('conversation_threads', 'kind')) {
            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->dropIndex('conversation_threads_company_kind_index');
            });

            Schema::table('conversation_threads', function (Blueprint $table): void {
                $table->dropColumn(['kind', 'created_by']);
            });
        }

        Schema::dropIfExists('conversation_participants');
    }
};
