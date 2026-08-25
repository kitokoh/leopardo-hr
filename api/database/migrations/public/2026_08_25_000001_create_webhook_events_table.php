<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5444 — Idempotence persistée des webhooks entrants.
 *
 * Une ligne par événement reçu (unique source + event_id) : le premier
 * traitement réserve la ligne (`response_code = 0`, verrou atomique via la
 * contrainte unique), les redelivrances du même événement sont rejouées avec
 * la réponse mémorisée — zéro effet double (paiement, relance, mail, lead).
 *
 * Schéma `public` (plateforme) : les webhooks sont publics par nature
 * (fournisseurs tiers, pas d'auth tenant) et l'unicité d'un événement est
 * globale à la plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->string('event_id', 191);
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('response_code')->default(0);
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->unique(['source', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
