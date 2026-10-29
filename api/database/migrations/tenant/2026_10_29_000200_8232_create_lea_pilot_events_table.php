<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8232 (BOS-044) — événements du pilote Léa.
 *
 * ## Pourquoi une table, alors que `lea.intent.*` est déjà journalisé
 *
 * Le parcours Léa trace chaque étape dans les logs (`lea.intent.plan_ready`,
 * `.clarification`, `.unavailable`, `.confirmed`…). Ces logs sont parfaits
 * pour le débogage et inutilisables pour la mesure : ils ne sont pas
 * interrogeables en SQL, leur rétention est courte, et un agrégat « taux de
 * fallback sur la fenêtre du pilote » demanderait d'aspirer des fichiers.
 *
 * Or le critère d'acceptation de #8232 est chiffré : fallback < 30 %,
 * complétion Léa ≥ formulaire, coût/onboarding sous le seuil. Sans table, la
 * décision GA / itération / retrait reposerait sur une impression.
 *
 * ## Ce que la table ne contient PAS
 *
 * Aucune description libre, aucune réponse d'entretien, aucun texte généré :
 * uniquement des ÉNUMÉRATIONS et des compteurs. C'est la revue RGPD du même
 * critère — un audit qui stockerait la description saisie par le client
 * (« une agence de voyages à Dakar avec 25 salariés ») créerait un traitement
 * de données d'entreprise sans base légale propre, pour un besoin qui est
 * purement statistique.
 *
 * `correlation_id` est conservé : il permet de relier les événements d'un
 * même parcours sans jamais identifier la personne (c'est un UUID de requête,
 * pas un identifiant utilisateur).
 *
 * Additive et idempotente ; `down()` ne détruit que ce que `up()` a créé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('lea_pilot_events')) {
            Schema::create('lea_pilot_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                // Étape du parcours : interpret | clarify | confirm.
                $table->string('stage', 20);

                // Résultat normalisé : plan_ready | clarification |
                // fallback_required | completed | stale_confirmation.
                $table->string('outcome', 30);

                // Code stable du fallback (llm_unavailable, invalid_json,
                // injection_suspected, plan_token_mismatch…). NULL sinon.
                $table->string('reason_code', 60)->nullable();

                // UUID de requête : relie les étapes d'un même parcours.
                $table->string('correlation_id', 64)->nullable();

                // Le parcours a-t-il été mené par Léa (true) ou par
                // l'entretien déterministe (false) ? Permet la comparaison
                // « complétion Léa vs formulaire » sans jointure fragile.
                $table->boolean('via_lea')->default(true);

                // Nombre de tours de clarification consommés (0, 1, 2).
                $table->unsignedSmallInteger('clarification_rounds')->default(0);

                // L'utilisateur a-t-il modifié le plan proposé avant de
                // confirmer ? Mesure le « taux de modification du résumé ».
                $table->boolean('answers_modified')->default(false);

                $table->timestampTz('created_at')->useCurrent();

                $table->index(['company_id', 'created_at'], 'lea_pilot_events_company_created_index');
                $table->index(['stage', 'outcome'], 'lea_pilot_events_stage_outcome_index');
                $table->index('correlation_id', 'lea_pilot_events_correlation_index');
            });

            DB::statement(
                "COMMENT ON TABLE lea_pilot_events IS 'Evenements du pilote Lea (BOS-044/#8232) - enums et compteurs uniquement, jamais de texte libre ni de reponse d entretien (revue RGPD).'"
            );
        }

        // Allowlists d'états : une valeur inventée ne doit pas entrer en base
        // même par INSERT direct (même doctrine que #8722 / #8723).
        DB::statement('ALTER TABLE lea_pilot_events DROP CONSTRAINT IF EXISTS lea_pilot_events_stage_allowed');
        DB::statement(
            'ALTER TABLE lea_pilot_events ADD CONSTRAINT lea_pilot_events_stage_allowed '
            ."CHECK (stage IN ('interpret', 'clarify', 'confirm'))"
        );

        DB::statement('ALTER TABLE lea_pilot_events DROP CONSTRAINT IF EXISTS lea_pilot_events_outcome_allowed');
        DB::statement(
            'ALTER TABLE lea_pilot_events ADD CONSTRAINT lea_pilot_events_outcome_allowed '
            ."CHECK (outcome IN ('plan_ready', 'clarification', 'fallback_required', 'completed', 'stale_confirmation'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lea_pilot_events');
    }
};
