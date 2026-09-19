<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7690 (R5 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.5)
 * — reponses assistees : politique par boite × categorie
 * (off/draft/confirm/auto) et file Pending durable des propositions de
 * reponse generees par l'IA.
 *
 * - `communication_reply_policies`  : politique choisie par l'UTILISATEUR
 *   pour SA boite (integration R1) et une categorie de la taxonomie R3 —
 *   UNIQUE integration×categorie, defaut implicite `off` (aucune ligne =
 *   aucune proposition). `auto` reste un opt-in explicite et n'est JAMAIS
 *   accepte sur les categories finance/RH/juridique (liste bloquee en dur,
 *   `CommunicationReplyPolicy::BLOCKED_AUTO_CATEGORIES`).
 * - `communication_pending_replies` : file Pending durable (equivalent
 *   PERSISTANT du PendingActionStore cache — une validation humaine peut
 *   arriver des jours plus tard, un TTL cache de 15 min ne convient pas) ET
 *   table de deduplication : UNIQUE (company, message) — une seule
 *   proposition par message entrant. Statuts : pending -> sent | rejected |
 *   skipped | failed ; `drafted` est terminal (brouillon depose chez Gmail).
 *   Le corps genere est CHIFFRE au repos (cast encrypted, pattern R2).
 * - `communication_reply_logs`      : journal d'audit APPEND-ONLY de chaque
 *   decision (proposed/draft_created/edited/approved/rejected/sent/
 *   auto_sent/skipped/failed + code machine). Sans FK vers la file :
 *   l'audit survit aux purges de fil, mais il est purge PAR INTEGRATION a
 *   la revocation (droit a l'effacement R2).
 *
 * Isolation : `company_id` uuid indexe partout. Migration reentrante
 * (`schemaTableExists`, garde #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('communication_reply_policies')) {
            Schema::create('communication_reply_policies', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->foreignUuid('integration_id')
                    ->constrained('communication_integrations')
                    ->cascadeOnDelete();
                // Cle de categorie de la taxonomie R3 (communication_categories.key).
                $table->string('category_key', 64);
                // off | draft | confirm | auto — l'API refuse `auto` sur les
                // categories a risque (liste bloquee en dur) et exige les
                // scopes Gmail adequats.
                $table->string('policy', 10)->default('off');
                $table->timestamps();

                $table->unique(
                    ['integration_id', 'category_key'],
                    'communication_reply_policies_integration_category_unique'
                );
            });
        }

        if (! schemaTableExists('communication_pending_replies')) {
            Schema::create('communication_pending_replies', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('integration_id')->index();
                $table->foreignUuid('thread_id')
                    ->constrained('communication_threads')
                    ->cascadeOnDelete();
                // Message ENTRANT auquel la proposition repond (reference,
                // pas de FK : la ligne de dedup survit a un resync).
                $table->uuid('message_id');
                $table->string('category_key', 64);
                // Politique appliquee au moment de la proposition (draft |
                // confirm | auto) — snapshot : un changement de politique
                // posterieur ne reinterprete pas la file.
                $table->string('mode', 10);
                $table->string('to_email');
                $table->string('subject', 998)->nullable();
                // Corps genere par l'IA — CHIFFRE au repos (cast encrypted).
                $table->text('body')->nullable();
                $table->string('ai_language', 8)->nullable();
                $table->unsignedSmallInteger('ai_confidence')->nullable();
                $table->string('status', 20)->default('pending')->index();
                // Code machine du garde-fou qui a arrete l'envoi (opted_out,
                // consent_blocked, missing_send_scope, quiet_hours…).
                $table->string('skip_reason', 64)->nullable();
                $table->timestamp('edited_at')->nullable();
                // Employe qui a approuve/rejete (validation humaine).
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                // Id du brouillon Gmail depose (mode draft).
                $table->string('gmail_draft_id')->nullable();
                // Id Gmail du message effectivement envoye (confirm/auto).
                $table->string('sent_gmail_message_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                // DEDUPLICATION : une seule proposition par message entrant.
                $table->unique(
                    ['company_id', 'message_id'],
                    'communication_pending_replies_dedup_unique'
                );
            });
        }

        if (! schemaTableExists('communication_reply_logs')) {
            Schema::create('communication_reply_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('integration_id')->index();
                // References sans FK : l'audit est append-only et survit aux
                // purges de fils/file (purge par integration a la revocation).
                $table->uuid('pending_reply_id')->nullable();
                $table->uuid('thread_id')->nullable();
                $table->string('to_email')->nullable();
                // proposed | draft_created | edited | approved | rejected |
                // sent | auto_sent | deferred | skipped | failed.
                $table->string('action', 20);
                // Code machine (garde-fou, decision) — jamais de contenu.
                $table->string('reason', 64)->nullable();
                // Employe a l'origine d'une decision humaine (approve/reject/edit).
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_reply_logs');
        Schema::dropIfExists('communication_pending_replies');
        Schema::dropIfExists('communication_reply_policies');
    }
};
