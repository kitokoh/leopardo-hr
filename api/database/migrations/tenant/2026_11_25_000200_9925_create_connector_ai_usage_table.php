<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9925 (BC-14 INTEGRATION) — suivi des tokens consommés par connexion IA.
 *
 * Quand un TENANT active un modèle d'IA (une connexion `ai_*` du catalogue),
 * chaque appel au modèle consomme des tokens. Cette table les comptabilise par
 * connexion et par mois, pour :
 *   - afficher la consommation réelle dans l'onglet Connecteurs ;
 *   - appliquer le budget mensuel optionnel (`monthly_token_budget` de la
 *     connexion) en fail-closed : au-delà, les appels sont refusés.
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, AUCUNE FK vers
 * `public.companies` (conventions migrations tenant §2.6) ; le modèle porte
 * `BelongsToCompany` (scope + fail-closed #3727).
 *
 * Une ligne = un mois de consommation d'une connexion. `input_tokens` et
 * `output_tokens` sont cumulés ; `total_tokens` est une colonne calculée à
 * l'écriture pour éviter de re-sommer à chaque lecture.
 *
 * Migration réentrante et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('connector_ai_usage')) {
            return;
        }

        Schema::create('connector_ai_usage', function (Blueprint $table): void {
            $table->id();

            $table->uuid('company_id')->index();

            // Référence `connector_connections.id` (tenant). Pas de FK : une
            // connexion supprimée ne doit pas bloquer la purge de son historique.
            $table->unsignedBigInteger('connection_id')->index();

            // Mois de consommation (YYYY-MM) : clé de regroupement du budget.
            $table->string('period', 7)->index();

            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);

            $table->timestamps();

            // Une seule ligne par (connexion, mois) : on incrémente, on ne
            // duplique pas.
            $table->unique(['connection_id', 'period'], 'connector_ai_usage_connection_period_unique');

            // Requête de l'écran : « la consommation de ce tenant, par connexion ».
            $table->index(['company_id', 'connection_id', 'period'], 'connector_ai_usage_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_ai_usage');
    }
};