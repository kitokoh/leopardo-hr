<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7687 (R2 de l'epique Communication R0->R6, spec
 * docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md §3.2) — fils de
 * discussion Gmail synchronises : une ligne = UN thread Gmail d'UNE boite
 * connectee (`communication_integrations`, R1 #7686).
 *
 * Minimisation des donnees (exigence issue) : le thread ne porte que des
 * METADONNEES — identifiant Gmail, sujet, extrait (snippet) du dernier
 * message, date du dernier message, compteur. AUCUN corps ici (le corps,
 * chiffre au repos, vit sur `communication_messages`), AUCUNE piece jointe.
 *
 * Anti-doublons (critere « resync sans doublons ») : UNIQUE
 * (company_id, integration_id, gmail_thread_id) — un resync (incremental ou
 * full apres expiration du historyId) met a jour la ligne, jamais ne la
 * duplique.
 *
 * Purge (critere « purge complete a la deconnexion ») : FK
 * `integration_id` cascadeOnDelete + suppression explicite lors de la
 * revocation R1 (l'integration revoquee reste en base pour l'audit, ses
 * threads/messages NON).
 *
 * Isolation : `company_id` (uuid indexe) porte le tenant, AUCUNE FK vers
 * `public.companies` (conventions migrations tenant §2.6). Migration
 * reentrante (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('communication_threads')) {
            return;
        }

        Schema::create('communication_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();

            // Boite proprietaire du fil (table tenant, meme schema).
            $table->uuid('integration_id');

            // Identifiant Gmail du thread : la cle d'idempotence du resync.
            $table->string('gmail_thread_id', 32);

            // Metadonnees du fil (minimisation : rien d'autre).
            $table->string('subject', 998)->nullable();
            // Snippet Gmail du message le plus recent (extrait court, jamais
            // le corps complet — le corps chiffre vit sur les messages).
            $table->string('snippet', 500)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            // Date du message le plus recent : sert au tri « 50 derniers fils ».
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();

            // Resync sans doublons : un thread Gmail = une ligne par boite.
            $table->unique(
                ['company_id', 'integration_id', 'gmail_thread_id'],
                'communication_threads_unique'
            );

            $table->foreign('integration_id')
                ->references('id')
                ->on('communication_integrations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_threads');
    }
};
