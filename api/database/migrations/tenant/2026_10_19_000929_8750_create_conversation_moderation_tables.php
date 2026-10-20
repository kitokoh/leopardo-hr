<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8750 [BOS-CHAT] — P5 : modération de base.
 *
 * Deux tables, aucune modification du module existant :
 *
 *  - `conversation_blocks` — un employé bloque un autre. Le blocage est
 *    **unilatéral** et ne supprime rien : il empêche la création d'un nouveau
 *    fil direct et l'ajout du bloqué dans un groupe dont le bloqueur est
 *    admin (effets appliqués par `ConversationModerationService`).
 *  - `conversation_reports` — signalement d'un message (ou d'un compte) par un
 *    participant. Le signalement est traité par un `manager` du MÊME tenant :
 *    aucun accès cross-tenant, et le contenu n'est jamais rendu public.
 *
 * Ces tables sont volontairement dans le schéma tenant : un signalement
 * n'existe pas hors de l'organisation où il a été émis.
 *
 * Conventions : `docs/architecture/MIGRATIONS_CONVENTIONS.md`.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('conversation_blocks')) {
            Schema::create('conversation_blocks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('blocker_id');
                $table->unsignedInteger('blocked_id');
                $table->string('reason', 200)->nullable();
                $table->timestampTz('created_at')->useCurrent();

                // Un blocage est unique par paire : re-bloquer est idempotent.
                $table->unique(['company_id', 'blocker_id', 'blocked_id'], 'conversation_blocks_pair_unique');
                $table->index(['company_id', 'blocked_id'], 'conversation_blocks_blocked_index');
            });

            DB::statement("COMMENT ON TABLE conversation_blocks IS '[BOS-CHAT #8750] Blocage unilatéral entre deux employés : empêche la création d''un fil direct et l''ajout dans un groupe, sans supprimer l''historique.]'");
        }

        if (! schemaTableExists('conversation_reports')) {
            Schema::create('conversation_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('reporter_id');
                // Le signalement peut porter sur un message précis OU sur un
                // compte : les deux colonnes sont nullables, jamais les deux à
                // la fois (contrainte CHECK ci-dessous).
                $table->unsignedBigInteger('conversation_thread_id')->nullable();
                $table->unsignedBigInteger('conversation_message_id')->nullable();
                $table->unsignedInteger('reported_employee_id')->nullable();
                $table->string('reason', 40);
                $table->string('details', 1000)->nullable();
                // open | reviewed | dismissed — traité par un manager du tenant.
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('reviewed_by_id')->nullable();
                $table->timestampTz('reviewed_at')->nullable();
                $table->string('resolution', 500)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'conversation_reports_company_status_index');
                $table->index(['company_id', 'conversation_thread_id'], 'conversation_reports_company_thread_index');

                $table->foreign('conversation_thread_id')
                    ->references('id')->on('conversation_threads')
                    ->nullOnDelete();
            });

            // Un signalement porte sur un message ou sur un compte, mais pas
            // sur « rien » : une ligne sans cible ne serait pas traitable.
            DB::statement(<<<'SQL'
                ALTER TABLE conversation_reports
                ADD CONSTRAINT conversation_reports_target_check
                CHECK (conversation_message_id IS NOT NULL OR reported_employee_id IS NOT NULL)
            SQL);

            DB::statement("COMMENT ON TABLE conversation_reports IS '[BOS-CHAT #8750] Signalement d''un message ou d''un compte, traité par un manager du même tenant uniquement.]'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reports');
        Schema::dropIfExists('conversation_blocks');
    }
};
