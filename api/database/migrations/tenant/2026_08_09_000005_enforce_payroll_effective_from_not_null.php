<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S-3 (#1663) — Durcissement paie : `effective_from` NOT NULL (additif).
 *
 * Contexte audit expert 2026-08-09 : `social_contributions.effective_from`
 * (et `tax_slabs.effective_from`) peut être NULL sur certains environnements
 * déjà migrés → risque de 500 quand le code suppose une date non nulle
 * (temporal versioning PA2-ARCH-004, `asOf()`).
 *
 * Migration ADDITIVE et idempotente :
 *   1. backfill des lignes NULL avec `created_at::date` (date la plus
 *      proche du vrai contexte métier) — jamais de perte de ligne ;
 *   2. `ALTER COLUMN ... SET NOT NULL` seulement si la colonne est
 *      encore nullable (les env neufs issus de
 *      2026_05_10_100001_create_payroll_engine_tables.php sont déjà NOT NULL).
 *
 * S'applique aussi bien sur un env neuf (no-op) que sur un env déjà migré.
 */
return new class extends Migration
{
    private const TABLES = [
        'tax_slabs',
        'social_contributions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $schema = resolveTableSchema($table);
            if ($schema === null) {
                continue;
            }

            $nullable = DB::selectOne(
                "SELECT is_nullable
                   FROM information_schema.columns
                  WHERE table_schema = ?
                    AND table_name = ?
                    AND column_name = 'effective_from'",
                [$schema, $table]
            );

            if ($nullable === null || $nullable->is_nullable !== 'YES') {
                continue; // Déjà NOT NULL — rien à faire (env neuf).
            }

            // Backfill : jamais de NULL résiduel avant le SET NOT NULL.
            DB::statement(
                "UPDATE {$schema}.{$table}
                    SET effective_from = COALESCE(created_at::date, '1970-01-01'::date)
                  WHERE effective_from IS NULL"
            );

            DB::statement(
                "ALTER TABLE {$schema}.{$table} ALTER COLUMN effective_from SET NOT NULL"
            );
        }
    }

    public function down(): void
    {
        // Rétrograder vers nullable serait une régression de la spec S-3 :
        // on ne défait pas le durcissement. No-op assumé.
    }
};
