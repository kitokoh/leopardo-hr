<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7687 (R2 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.2)
 * — messages Gmail synchronises, rattaches a un `communication_threads`.
 *
 * MINIMISATION DES DONNEES (exigence issue, §5.2 de la spec) :
 * - metadonnees UTILES uniquement : expediteur, destinataires (adresses),
 *   sujet, date, labels Gmail, snippet ;
 * - references de fil RFC 5322 (`Message-ID`, `In-Reply-To`) pour le
 *   threading et les relances R4 — jamais les headers complets ;
 * - `body` : partie text/plain UNIQUEMENT, bornee, CHIFFREE AU REPOS
 *   (cast `encrypted` du modele, pattern tokens R1/#7686 — colonne TEXT car
 *   le payload chiffre est volumineux). Jamais de HTML brut, jamais de raw
 *   MIME ;
 * - pieces jointes NON stockees : seules les REFERENCES Gmail
 *   (attachmentId, filename, mimeType, size) sont conservees en JSON pour
 *   un acces a la demande via l'API Gmail (V1.1+).
 *
 * Anti-doublons : UNIQUE (company_id, integration_id, gmail_message_id) —
 * l'ingestion (full ou incrementale) est idempotente par construction.
 *
 * Purge a la deconnexion : cascade via `thread_id` + `integration_id`
 * indexe pour la suppression directe lors de la revocation R1.
 *
 * Isolation : `company_id` (uuid indexe), aucune FK vers public.companies.
 * Migration reentrante (`schemaTableExists`, garde #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('communication_messages')) {
            return;
        }

        Schema::create('communication_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();

            $table->uuid('thread_id');
            // Denormalise pour la purge directe a la revocation (sans join).
            $table->uuid('integration_id')->index();

            // Identifiant Gmail du message : cle d'idempotence de l'ingestion.
            $table->string('gmail_message_id', 32);

            // References RFC 5322 pour le threading / relances R4 (jamais les
            // headers complets — minimisation).
            $table->string('internet_message_id', 998)->nullable();
            $table->string('in_reply_to', 998)->nullable();

            // Metadonnees utiles : adresses seules (pas de display name
            // au-dela de ce que porte le header From).
            $table->string('from_email')->nullable();
            $table->json('to_emails')->nullable();
            $table->json('cc_emails')->nullable();

            $table->string('subject', 998)->nullable();
            // Snippet Gmail (extrait court fourni par l'API).
            $table->string('snippet', 500)->nullable();

            // Corps text/plain borne, cast `encrypted` cote modele : JAMAIS
            // en clair en base (exigence « corps chiffre au repos »).
            $table->text('body')->nullable();

            // Labels Gmail (INBOX, SENT, UNREAD…) : base de la classification R3.
            $table->json('labels')->nullable();

            // References de pieces jointes (attachmentId/filename/mimeType/size)
            // — le CONTENU n'est jamais stocke (exigence issue).
            $table->json('attachment_refs')->nullable();

            // Date interne Gmail (reception/envoi) : tri chronologique du fil.
            $table->timestamp('sent_at')->nullable()->index();

            $table->timestamps();

            $table->unique(
                ['company_id', 'integration_id', 'gmail_message_id'],
                'communication_messages_unique'
            );

            $table->foreign('thread_id')
                ->references('id')
                ->on('communication_threads')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
