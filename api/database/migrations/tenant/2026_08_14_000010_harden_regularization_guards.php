<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1942 — Durcissement des garde-fous de régularisation (#1818).
 *
 * Le run de régularisation ne doit jamais être dupliqué (double-clic,
 * requêtes concurrentes) : index UNIQUE PARTIEL sur `original_run_id` pour
 * les régularisations ACTIVES (tout statut sauf cancelled/error). Contrainte
 * CHECK sur `type` (standard | regularization) — le schéma était permissif.
 *
 * F-17 (#1595/#1933) : accès qualifiés par schéma résolu via
 * `current_schemas(false)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('payroll_runs');

        if ($schema === null) {
            return; // Table absente dans ce contexte.
        }

        $qualified = $schema.'.payroll_runs';

        // Anti-double-application : une seule régularisation ACTIVE par run
        // original (draft → paid inclus ; cancelled/error libèrent la place).
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS payroll_runs_original_active_unique
                ON %s (original_run_id)
             WHERE original_run_id IS NOT NULL
               AND type = \'regularization\'
               AND status NOT IN (\'cancelled\', \'error\')',
            $qualified
        ));

        // Le champ `type` n'admet que deux valeurs (le modèle expose les
        // constantes TYPE_STANDARD / TYPE_REGULARIZATION).
        DB::statement(sprintf(
            'ALTER TABLE %s DROP CONSTRAINT IF EXISTS payroll_runs_type_check',
            $qualified
        ));
        DB::statement(sprintf(
            "ALTER TABLE %s ADD CONSTRAINT payroll_runs_type_check CHECK (type IN ('standard', 'regularization'))",
            $qualified
        ));
    }

    public function down(): void
    {
        $schema = resolveTableSchema('payroll_runs');

        if ($schema === null) {
            return;
        }

        DB::statement(sprintf(
            'DROP INDEX IF EXISTS %s.payroll_runs_original_active_unique',
            $schema
        ));
        DB::statement(sprintf(
            'ALTER TABLE %s.payroll_runs DROP CONSTRAINT IF EXISTS payroll_runs_type_check',
            $schema
        ));
    }
};
