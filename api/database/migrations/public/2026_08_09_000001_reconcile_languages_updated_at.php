<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        if (! Schema::hasColumn('languages', 'updated_at')) {
            Schema::table('languages', function (Blueprint $table): void {
                $table->timestampTz('updated_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('languages', 'name_native')) {
            Schema::table('languages', function (Blueprint $table): void {
                $table->string('name_native', 50)->default('');
            });
        }
    }

    public function down(): void
    {
        // Rétrograde non destructive — additive assumée.
    }
};
