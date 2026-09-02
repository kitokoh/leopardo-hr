<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6694 — Réponses du survey de pré-qualification des solutions sectorielles
 * (wizard vitrine « Je suis restaurateur »).
 *
 * Table PUBLIQUE (pré-inscription, pas de tenant) : les réponses sont
 * anonymes (aucune PII — RGPD) ; le lien avec le lead (email + consentement,
 * PR #6705) reste optionnel via `lead_email_hash` (haché, jamais en clair).
 * Agrégée par l'écran admin « Surveys de solutions » (stats de conversion).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solution_survey_responses')) {
            return;
        }

        Schema::create('solution_survey_responses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('solution_code', 50)->index();          // ex. 'restaurant'
            $table->json('answers')->default('{}');                // {clé_question: valeur}
            $table->json('suggested_packages')->default('[]');     // [{key, priority, reason_key}]
            $table->unsignedSmallInteger('total_packages')->default(0);
            $table->string('lead_email_hash', 64)->nullable()->index(); // sha256(email) si lead joint (#6705)
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['solution_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_survey_responses');
    }
};
