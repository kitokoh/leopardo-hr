<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7689 (R4 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.4)
 * — moteur de relances automatiques : regles par utilisateur, sequences
 * multi-etapes (max 3), file d'attente deduplication, opt-out et journal
 * d'audit des envois.
 *
 * - `communication_follow_up_rules`   : regle par BOITE (integration R1) —
 *   la relance part du Gmail de l'utilisateur, jamais d'une autre boite.
 * - `communication_follow_up_steps`   : sequence ordonnee (position 1..3,
 *   UNIQUE rule×position), delai en jours et gabarit `EmailTemplateRegistry`.
 * - `communication_follow_ups`        : file d'attente ET table de
 *   deduplication (exigence issue : « relance part une seule fois par
 *   echeance ») — UNIQUE (company, thread, rule, step_position). Statuts :
 *   pending -> sent | skipped | failed | cancelled.
 * - `communication_follow_up_opt_outs`: exclusion locale d'un destinataire
 *   (UNIQUE company×email) — en plus du consentement/unsubscribe CRM.
 * - `communication_follow_up_logs`    : journal d'audit APPEND-ONLY de
 *   chaque decision d'envoi (sent/skipped/failed/cancelled + code machine).
 *   Sans FK vers la file : l'audit survit aux purges de fil, mais il est
 *   purge PAR INTEGRATION a la revocation (droit a l'effacement R2).
 *
 * Isolation : `company_id` uuid indexe partout. Migration reentrante
 * (`schemaTableExists`, garde #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('communication_follow_up_rules')) {
            Schema::create('communication_follow_up_rules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->foreignUuid('integration_id')
                    ->constrained('communication_integrations')
                    ->cascadeOnDelete();
                $table->string('name', 128);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(
                    ['company_id', 'integration_id'],
                    'communication_follow_up_rules_company_integration_idx'
                );
            });
        }

        if (! schemaTableExists('communication_follow_up_steps')) {
            Schema::create('communication_follow_up_steps', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->foreignUuid('rule_id')
                    ->constrained('communication_follow_up_rules')
                    ->cascadeOnDelete();
                // Position 1..3 (max 3 relances, spec §3.4) — l'API valide la borne.
                $table->unsignedSmallInteger('position');
                // Delai en jours depuis le message sortant (etape 1) ou la
                // relance precedente (etapes suivantes).
                $table->unsignedSmallInteger('delay_days');
                // Cle du gabarit dans EmailTemplateRegistry (surcharge par locale).
                $table->string('template_key', 64)->default('communication_follow_up');
                $table->timestamps();

                $table->unique(['rule_id', 'position'], 'communication_follow_up_steps_rule_position_unique');
            });
        }

        if (! schemaTableExists('communication_follow_ups')) {
            Schema::create('communication_follow_ups', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->foreignUuid('rule_id')
                    ->constrained('communication_follow_up_rules')
                    ->cascadeOnDelete();
                $table->uuid('integration_id')->index();
                $table->foreignUuid('thread_id')
                    ->constrained('communication_threads')
                    ->cascadeOnDelete();
                // Message sortant ANCRE de l'echeance (reference, pas de FK :
                // la ligne de dedup survit a un resync du message).
                $table->uuid('message_id')->nullable();
                $table->unsignedSmallInteger('step_position');
                $table->string('contact_email');
                $table->timestamp('scheduled_for')->index();
                $table->string('status', 20)->default('pending')->index();
                // Code machine du garde-fou qui a arrete l'envoi (replied,
                // opted_out, consent_blocked, mailing_list, missing_send_scope…).
                $table->string('skip_reason', 64)->nullable();
                $table->timestamp('sent_at')->nullable();
                // Id Gmail du message de relance effectivement envoye.
                $table->string('sent_gmail_message_id')->nullable();
                $table->timestamps();

                // DEDUPLICATION (exigence issue) : une seule ligne par echeance.
                $table->unique(
                    ['company_id', 'thread_id', 'rule_id', 'step_position'],
                    'communication_follow_ups_dedup_unique'
                );
            });
        }

        if (! schemaTableExists('communication_follow_up_opt_outs')) {
            Schema::create('communication_follow_up_opt_outs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('email');
                // manual (ajout par un employe) | unsubscribe (retour destinataire).
                $table->string('source', 20)->default('manual');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'email'], 'communication_follow_up_opt_outs_company_email_unique');
            });
        }

        if (! schemaTableExists('communication_follow_up_logs')) {
            Schema::create('communication_follow_up_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('follow_up_id')->nullable()->index();
                $table->uuid('integration_id')->index();
                $table->uuid('thread_id')->nullable();
                $table->unsignedSmallInteger('step_position')->nullable();
                $table->string('contact_email')->nullable();
                // sent | skipped | failed | cancelled.
                $table->string('action', 20)->index();
                // Code machine (jamais de texte libre, jamais de payload Google).
                $table->string('reason', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_follow_up_logs');
        Schema::dropIfExists('communication_follow_up_opt_outs');
        Schema::dropIfExists('communication_follow_ups');
        Schema::dropIfExists('communication_follow_up_steps');
        Schema::dropIfExists('communication_follow_up_rules');
    }
};
