<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CI main rouge 2026-08-09 — `languages.updated_at` absent sur les env déjà
 * migrés : `2026_04_01_000003` crée la table sans `updated_at`, et l'ancienne
 * version de `2026_04_24_000015` retournait immédiatement (hasTable) sans
 * réconcilier — tout insert Eloquent (`Language::create`) échouait
 * (`column updated_at does not exist`). Migration additive de rattrapage :
 * ajoute `updated_at` (et `name_native` si manquant) sur les env déjà migrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('languages');

        if ($schema === null) {
            return; // Table absente dans ce contexte.
        }

        // `hasColumn()` followed by `ALTER TABLE` is racy when Render starts
        // more than one application process during a migration. PostgreSQL's
        // atomic IF NOT EXISTS keeps the retry idempotent without swallowing
        // unrelated schema errors.
        $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';
        DB::statement("ALTER TABLE {$quotedSchema}.\"languages\" ADD COLUMN IF NOT EXISTS \"updated_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP");
        DB::statement("ALTER TABLE {$quotedSchema}.\"languages\" ADD COLUMN IF NOT EXISTS \"name_native\" VARCHAR(50) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        // Rétrograde non destructive — additive assumée.
    }
};
