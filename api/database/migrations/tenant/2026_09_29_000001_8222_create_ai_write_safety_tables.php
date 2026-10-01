<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-032 (#8222) — idempotence métier des write-tools IA.
 *
 * Deux tables ADDITIVES, sans aucune modification de l'existant :
 *  - `ai_write_idempotency` : une intention confirmée (clé = conversation +
 *    empreinte des arguments) n'a qu'un seul effet ; le rejeu (retry réseau,
 *    reconfirmation) retrouve le résultat initial au lieu de ré-exécuter.
 *  - `ai_pending_actions` : backend base de données du PendingActionStore,
 *    utilisé quand le driver de cache n'est pas partagé entre workers
 *    (file/array) — une action sensible en attente ne peut plus « disparaître »
 *    selon le worker qui reçoit la confirmation.
 *
 * Rollback : down() droppable sans impact (aucune FK entrante, aucune donnée
 * reprise par d'autres tables).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('ai_write_idempotency')) {
            Schema::create('ai_write_idempotency', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('tool', 100);
                // sha256(company_id | conversation_id | tool | empreinte args).
                $table->char('idempotency_key', 64);
                // sha256 de la représentation canonique des arguments (tri
                // récursif des clés + JSON) — rejouable pour détecter une
                // reconfirmation identique dans la même conversation.
                $table->char('arguments_hash', 64);
                // Lien avec la proposition confirmée (retry réseau sur le MÊME
                // pending_action_id → même réponse, même après consommation
                // du pending one-shot).
                $table->string('pending_action_id', 64)->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->jsonb('result');
                $table->timestampTz('expires_at')->index();
                $table->timestampTz('created_at')->useCurrent();

                $table->unique(['company_id', 'idempotency_key']);
                $table->foreign('conversation_id')->references('id')->on('ai_conversations')->nullOnDelete();
            });
        }

        if (! schemaTableExists('ai_pending_actions')) {
            Schema::create('ai_pending_actions', function (Blueprint $table) {
                // Identifiant = UUID texte (même format que la clé de cache
                // historique du PendingActionStore).
                $table->string('id', 64)->primary();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('user_id');
                $table->string('tool', 100);
                $table->jsonb('arguments');
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->timestampTz('expires_at')->index();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_write_idempotency');
        Schema::dropIfExists('ai_pending_actions');
    }
};
