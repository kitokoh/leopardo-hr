<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * APV L.10 — Nouvelles donnees via JSONB d'abord.
 *
 * Ajoute employees.metadata (JSONB) pour permettre des extensions de profil
 * sans migration (preferences, stades d'apprentissage, marqueurs module, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'metadata')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            });

            DB::statement("COMMENT ON COLUMN employees.metadata IS 'Champs d extension JSONB (APV L.10). Aucune donnee critique ici, uniquement des preferences / stades / marqueurs.'");
            DB::statement('CREATE INDEX IF NOT EXISTS employees_metadata_gin ON employees USING GIN (metadata)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS employees_metadata_gin');

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'metadata')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }
};
