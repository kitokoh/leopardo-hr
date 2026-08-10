<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec S-3 (#1663) — Durcissement paie : `social_contributions.effective_from` NOT NULL.
 *
 * Migration ADDITIVE : ne réécrit pas `2026_05_10_100001_create_payroll_engine_tables`
 * (déjà exécutée sur les env existants). Backfill des NULL puis contrainte
 * NOT NULL, en lisant le type réel via `information_schema` — inoffensive sur
 * les env où la colonne est déjà NOT NULL (env neufs) comme sur les env où
 * elle est restée nullable (500 potentiel N9P4).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('social_contributions');

        if ($schema === null) {
            return; // Table absente dans ce contexte — rien à durcir.
        }

        $column = DB::selectOne(
            'SELECT is_nullable
               FROM information_schema.columns
              WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?',
            [$schema, 'social_contributions', 'effective_from']
        );

        if ($column === null) {
            return; // Colonne absente — rien à durcir.
        }

        if ($column->is_nullable === 'NO') {
            return; // Déjà NOT NULL — aucune action (env neufs).
        }

        // Backfill : les lignes historiques sans date d'effet prennent la date
        // de création (ou une date de repli si `created_at` est nul).
        DB::statement(
            "UPDATE \"{$schema}\".\"social_contributions\"
                SET effective_from = COALESCE(created_at::date, DATE '2024-01-01')
              WHERE effective_from IS NULL"
        );

        DB::statement(
            "ALTER TABLE \"{$schema}\".\"social_contributions\" ALTER COLUMN effective_from SET NOT NULL"
        );
    }

    public function down(): void
    {
        // Rétrograde impossible sans perte d'information — migration additive assumée.
    }
};
