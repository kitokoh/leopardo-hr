<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEMAC/CM — Déclaration CNPS mensuelle (issue #1823) : matricule CNPS de
 * l'employé utilisé dans le format DAS (Déclaration et Attestation de
 * Salaires) camerounais. Additif nullable — les employés existants restent
 * exportés avec un fallback (identifiant interne) tant que le matricule
 * n'est pas renseigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema !== null && ! schemaHasColumn('employees', 'cnps_matricule')) {
            Schema::table("{$schema}.employees", function (Blueprint $table) {
                $table->string('cnps_matricule', 30)->nullable()->after('matricule');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema !== null && schemaHasColumn('employees', 'cnps_matricule')) {
            Schema::table("{$schema}.employees", function (Blueprint $table) {
                $table->dropColumn('cnps_matricule');
            });
        }
    }
};
