<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ML-04 — Ajouter preferred_language aux employees
 *
 * Langue préférée de l'employé pour ses notifications et son interface.
 * NULL = utilise la langue de la company (companies.language).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'preferred_language')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->char('preferred_language', 2)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'preferred_language')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('preferred_language');
            });
        }
    }
};
