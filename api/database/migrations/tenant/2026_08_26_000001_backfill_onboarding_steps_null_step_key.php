<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #R2 — Backfill des lignes `onboarding_steps` avec `step_key = NULL`.
 *
 * Contexte (bug #4188) : avant le correctif de `SeedDefaultSteps.php`,
 * le seeder passait `key`/`label` au lieu de `step_key`/`title`. Ces clés
 * n'étant pas fillable et `key` n'étant pas une colonne, chaque appel
 * insérait des lignes avec `step_key = NULL` et `title = NULL`.
 *
 * Ces lignes sont inutilisables :
 *   - la dédup pluck('step_key') renvoyait [null] → réinsertion à l'infini ;
 *   - le wizard frontend ne peut pas identifier l'étape sans `step_key` ;
 *   - la contrainte UNIQUE (company_id, step_key) est violée en production
 *     sur certains tenants (lignes orphelines dupliquées avec step_key NULL).
 *
 * Stratégie : supprimer les lignes orphelines (step_key NULL ou vide).
 * Le prochain appel à GET /onboarding-setup/checklist déclenchera le
 * seed paresseux (SeedDefaultSteps) et créera les 10 étapes correctes.
 *
 * Applicable dans le schéma shared_tenants (tables tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Supprimer toutes les lignes sans step_key identifiable.
        // set_config : force le search_path dans la transaction courante
        // (nécessaire dans les migrations tenant pour cibler shared_tenants).
        DB::statement("
            DELETE FROM onboarding_steps
            WHERE step_key IS NULL
               OR step_key = ''
        ");
    }

    public function down(): void
    {
        // Non réversible : les lignes orphelines supprimées seront
        // recréées correctement par le seed paresseux au prochain accès.
    }
};
