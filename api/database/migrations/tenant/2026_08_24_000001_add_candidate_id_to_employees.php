<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5261 — traçabilité du parcours candidat → employé.
 *
 * Ajoute `candidate_id` (nullable) sur `employees` : le lien vers
 * l'Applicant (module Recruitment) d'origine. Colonne additive, aucun
 * backfill — les employés existants gardent `candidate_id = NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->unsignedBigInteger('candidate_id')->nullable()->after('id');
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['candidate_id']);
            $table->dropColumn('candidate_id');
        });
    }
};
