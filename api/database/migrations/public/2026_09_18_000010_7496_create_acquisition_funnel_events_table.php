<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7496 — Événements d'étape du funnel d'acquisition.
 *
 * Chaque étape du tunnel (signup_view → … → interview_completed) est
 * horodatée et rattachée à un `correlation_id` pseudonyme généré par la
 * vitrine (sessionStorage, un identifiant par parcours). C'est la source du
 * dashboard admin des conversions par étape et par source.
 *
 * Volontairement GLOBALE (schéma public, pas de company_id) : un prospect
 * dans le tunnel n'appartient encore à aucun tenant — même pattern que
 * `marketing_leads` (pré-tenant, PA2-MKT-007).
 *
 * Aucune PII (critère 4 de #7496) : pas d'e-mail, pas de valeur de
 * formulaire, pas de réponse d'entretien — seulement le nom d'étape (liste
 * fermée validée côté contrôleur), la corrélation, l'attribution
 * (source/utm_*) et un contexte borné (clé d'étape, rang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acquisition_funnel_events')) {
            return;
        }

        Schema::create('acquisition_funnel_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 60);
            $table->string('correlation_id', 64);
            $table->string('source', 120)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            // Contexte non-PII : page, step_key, question_index, resend…
            $table->jsonb('context')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            // Requêtes du dashboard : agrégats par étape/jour et par source,
            // plus la reconstitution d'un parcours par corrélation.
            $table->index(['event', 'occurred_at']);
            $table->index(['correlation_id']);
            $table->index(['source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_funnel_events');
    }
};
