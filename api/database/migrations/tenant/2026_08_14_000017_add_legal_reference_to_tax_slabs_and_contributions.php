<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vague QA 2026-08-14 — référence légale sur les barèmes et cotisations.
 *
 * Issue #2188 : le champ `legal_reference` saisi dans TaxRatesView était
 * silencieusement ignoré (aucune colonne backend). Colonne additive nullable,
 * idempotente (IF NOT EXISTS) pour les environnements partiellement migrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tax_slabs', 'social_contributions'] as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table): void {
                $table->string('legal_reference', 200)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['tax_slabs', 'social_contributions'] as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('legal_reference');
            });
        }
    }
};
