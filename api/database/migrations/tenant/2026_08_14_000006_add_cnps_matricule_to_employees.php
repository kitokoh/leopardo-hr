<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEMAC/CM (#1823) — matricule CNPS employé pour la déclaration DAS.
 *
 * Migration additive : `employees.cnps_matricule` (nullable, libre) — le
 * numéro d'immatriculation CNPS Cameroun de chaque salarié, utilisé comme
 * première colonne de la déclaration mensuelle DAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('employees') || schemaHasColumn('employees', 'cnps_matricule')) {
            return;
        }

        Schema::table('employees', function (Blueprint $blueprint): void {
            $blueprint->string('cnps_matricule', 50)->nullable();
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('employees') || ! schemaHasColumn('employees', 'cnps_matricule')) {
            return;
        }

        Schema::table('employees', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('cnps_matricule');
        });
    }
};
